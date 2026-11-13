# Magento store: WhatsApp ordering (project README)

This is the Magento 2 side of a WhatsApp ordering solution. Customers of a B2C store verify their phone number, then
browse and order over WhatsApp with cash on delivery. A separate Spring Boot service, `chat-commerce-bot`, talks to
WhatsApp through Infobip and calls the REST endpoints in this repository. **Magento stays the single source of truth**
for customers, prices, stock and orders; the bot only holds conversation state.

Each custom module has its own README with endpoint details and test tables. This file is the map.

## At a glance

| | |
|---|---|
| Platform | Magento Open Source 2.4.8-p5, Inventory (MSI) enabled, simple products only for now |
| Local setup | Windows + WSL2 + Warden, `https://magento.test`, Valkey for cache/sessions, MariaDB 11.8 |
| Custom modules | `app/code/Vendor/PhoneVerification`, `app/code/Vendor/WhatsappOrdering` |
| Bot (other repo) | `chat-commerce-bot`: Spring Boot, Redis, Infobip |
| Payment | Cash on delivery only |
| Status | Phone verification done. SKU lookup, order preview and place-order endpoints built. Bot checkout buttons next. |

## How the pieces fit

```
Customer's WhatsApp -> Infobip -> chat-commerce-bot (webhook, Redis sessions) -> Magento REST (this repo)
```

1. The customer links a number on **My Account > Phone & WhatsApp** and proves it by sending `VERIFY ######` from that
   WhatsApp number. The bot passes the code to Magento, which marks the number verified.
2. A verified customer gets a menu in WhatsApp. They type SKUs and quantities. The bot asks Magento which SKUs can be
   ordered (`products/lookup`) and keeps the cart in its own Redis.
3. At checkout the bot asks Magento for a **preview** (default shipping address, shipping, tax, total, limits) and, after
   the customer confirms, **places** the order with an idempotency key so a retry can never order twice.

## Modules

### `Vendor_PhoneVerification`

Owns phone numbers, one-time codes and the decision "is this number verified". Adds `phone_number`,
`phone_verified`, `phone_verified_at` to `customer_entity` and the `phone_verification` table (HMAC of the code only,
never the code).

| Endpoint | Permission | Purpose |
|---|---|---|
| `POST /rest/V1/phone/verify` | `Vendor_PhoneVerification::verify` | Complete verification from `VERIFY ######` |
| `GET /rest/V1/phone/lookup` | `Vendor_PhoneVerification::lookup` | Is this number a verified customer? |

Settings: Stores > Configuration > Customers > **Phone Verification**.

### `Vendor_WhatsappOrdering`

Depends on `Vendor_PhoneVerification`. Owns what can be ordered and the order itself. One table:
`vendor_whatsapp_order` (idempotency keys).

| Endpoint | Permission | Purpose |
|---|---|---|
| `POST /rest/V1/whatsapp/products/lookup` | `Vendor_WhatsappOrdering::catalog_read` | Can these SKUs be ordered, name, price, salable quantity |
| `POST /rest/V1/whatsapp/orders/preview` | `Vendor_WhatsappOrdering::order_preview` | Address, shipping, totals, problems. Creates nothing |
| `POST /rest/V1/whatsapp/orders` | `Vendor_WhatsappOrdering::order_place` | Place the cash-on-delivery order; same idempotency key returns the same order |

Settings: Stores > Configuration > Sales > **WhatsApp Ordering**: shipping method code (default `flatrate_flatrate`),
payment method code (`cashondelivery`), maximum order value (5000), maximum items (50). `0` means no limit.

## Install on a fresh environment

1. Magento 2.4.8 with Inventory (MSI) and the cash on delivery payment method enabled
   (Stores > Configuration > Sales > Payment Methods).
2. Copy the two module folders into `app/code/Vendor/`. Unzip inside WSL, not from a Windows folder, or delete the
   `*:Zone.Identifier` files that Windows leaves behind.
3. In `warden shell`:
   ```bash
   bin/magento module:enable Vendor_PhoneVerification Vendor_WhatsappOrdering
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```
   Production mode also needs `bin/magento setup:di:compile` and `setup:static-content:deploy -f`.
4. Allow Integration tokens as bearer tokens (Magento 2.4.4+ blocks this by default):
   `bin/magento config:set oauth/consumer/enable_integration_as_bearer 1`, then `cache:flush`.
5. Create an Integration (System > Extensions > Integrations) for the bot. On the **API** tab tick:
    - Phone Verification: *Verify phone via messaging channel*, *Look up customer by phone*
    - WhatsApp Ordering: *Look up products by SKU*, *Preview an order*, *Place an order*

   Activate it and copy the **Access Token** (not the secret). The bot's configuration takes it; never paste it into
   chat, tickets or commits.
6. Set the WhatsApp sender number under Customers > Phone Verification, then the checkout settings under
   Sales > WhatsApp Ordering. Use a shipping code that appears in the preview's `available_shipping` list.
7. **Local stores only:** indexers must be realtime if nothing runs cron, or new products show a salable quantity of 0:
   ```bash
   bin/magento indexer:set-mode realtime
   bin/magento indexer:reindex
   bin/magento cache:flush
   ```

## Requirements for a customer to be able to order

- A customer account with a verified phone number.
- A **default shipping address** (and optionally a default billing address; shipping is used if none).
- Products that are simple, enabled, assigned to the default website and in stock.

## Testing

Each module README has a numbered table to run with curl. Keep the token out of your shell history:

```bash
read -rs TOKEN; export TOKEN
curl -sk -X POST https://magento.test/rest/V1/whatsapp/orders/preview \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"phone":"<verified number>","items":[{"sku":"<real SKU>","qty":1}]}'
```

`-k` skips certificate checking and is for the local Warden certificate only. Test orders can be cancelled or deleted in
the admin; cancelling returns the stock.

## Design decisions that matter here

- **Magento decides, the bot asks.** Totals, tax, stock and limits are Magento's own calculation, never the bot's.
- **Preview and place share one builder** (`OrderDraftBuilder`), so the order can never disagree with what the customer
  was shown. The preview uses an unsaved quote and leaves nothing behind.
- **Idempotency lives in the database,** not in the bot's Redis, so it survives a Redis restart.
- **Separate modules:** phone verification knows who the customer is; ordering knows what can be bought.
- **Least privilege:** each endpoint has its own permission on the Integration.
- **Every failure that is the customer's concern is returned as data** (`ok: false` plus a sentence), not thrown. Only
  malformed requests give HTTP 400.

## Known limits

- Simple products only; no backorders; judged for the default store view and website.
- Price shown by the lookup is the not-logged-in price; customer-group and tier prices are not applied (revisit for B2B).
- Delivery uses the default shipping address and one fixed shipping method.
- Number squatting: a number is unique per customer even before it is verified. Decide a fix before go-live.
- A PHP crash between claiming an idempotency key and placing the order leaves the key "pending"; the customer simply
  confirms again with a new key.
- Order emails follow the store's normal settings.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| 401 "consumer isn't authorized" | Permission not ticked on the Integration, or bearer tokens not enabled (install step 4) |
| Every product shows out of stock | Indexers on schedule without cron (install step 7) |
| Page looks stale after a module change | Browser cache; hard refresh or a private window |
| First call after a cache flush times out | Retry; the first request warms the caches |
| `ok: false`, "Delivery to your address is not available" | The configured shipping code is not in `available_shipping` |
| "We could not place your order right now" | Read `var/log/system.log` and `var/log/exception.log` for the real reason |
| New module class not found | Run `bin/magento setup:upgrade` and `cache:flush`; check the file is in the right folder |

## Git hygiene

- Commit `app/etc/config.php` after enabling modules.
- Do not commit database dumps (they hold customer data), tokens, or `*:Zone.Identifier` files.
- The bot lives in its own repository (`chat-commerce-bot`) with its own README.

## Not built yet

Reply buttons and the Confirm/Cancel flow in the bot, browse by category, search, order again, B2B pricing and
customer groups, WhatsApp message templates for messages outside the 24-hour window.
