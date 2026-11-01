# Vendor_WhatsappOrdering

Catalogue and (later) order endpoints for the WhatsApp ordering bot (`chat-commerce-bot`). It is separate from
`Vendor_PhoneVerification` on purpose: that module knows who a customer is, this one knows what can be ordered, and each
has its own permissions on the Magento Integration.

**Status:** SKU lookup is built and tested. Categories, search, frequent products and order creation come in later steps.

## Requirements

- Magento Open Source 2.4.8 (tested target), with Inventory (MSI) modules enabled, which is the default.
- Simple products only. Other product types are described as `unsupported_type`.

## Install

1. Unzip so the files end up in `app/code/Vendor/WhatsappOrdering` (unzip inside WSL, not from a Windows folder, to avoid
   `*:Zone.Identifier` files).
2. In `warden shell`:
   ```bash
   bin/magento module:enable Vendor_WhatsappOrdering
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```
   This module has no database changes. `app/etc/config.php` gains one line; commit it.
3. **System > Extensions > Integrations**, edit the Integration the bot uses, open the **API** tab and tick
   **WhatsApp Ordering > Look up products by SKU**. Save. The Access Token does not change.

## Endpoint: `POST /rest/V1/whatsapp/products/lookup`

Permission: `Vendor_WhatsappOrdering::catalog_read`. Header `Authorization: Bearer <Access Token>`.

Request: 1 to 20 SKUs, exactly as the customer typed them.

```json
{ "skus": ["24-MB01", "nope-123"] }
```

Response: one object per requested SKU, in the same order. A SKU that cannot be ordered is described, not an error.

```json
[
  { "requested_sku": "black-za", "found": true, "orderable": true,
    "sku": "black-za", "name": "Black Label", "price": 20, "currency": "ZAR", "salable_qty": 99999999 },
  { "requested_sku": "nope-123", "found": false, "orderable": false }
]
```

Magento leaves out fields whose value is null, so `reason` is absent on an orderable product, and a SKU that was not
found has only the first three fields. Treat a missing field as null.

| Field | Meaning |
|---|---|
| `requested_sku` | What was sent, trimmed |
| `found` | A product with this SKU exists (SKU matching ignores letter case) |
| `orderable` | True only if it can be ordered right now; then `reason` is absent |
| `reason` | `unsupported_type`, `disabled`, `not_on_website` or `out_of_stock` |
| `sku` | The SKU as stored in Magento (its letter case may differ from what was typed); use this one afterwards |
| `name`, `price`, `currency` | Name; the price a not-logged-in visitor sees (special prices included, tax shown the way the store is set up to show it); base currency code |
| `salable_qty` | Quantity that can be sold now from the website's stock (Inventory salable quantity, never negative) |

Errors: an empty list or more than 20 SKUs gives HTTP 400; a missing or wrong token gives 401; an Integration without the
permission gives 401 "consumer isn't authorized".

### What "orderable" checks, in order

1. The product type is `simple`.
2. The product is enabled.
3. It is assigned to the default website.
4. Inventory says it is salable for that website's stock (this respects reservations and backorder settings).

"Not Visible Individually" is allowed: ordering by SKU should work for products that are not listed in the catalogue.

### Known limits

- Everything is judged for the default store view and website, which is what a REST call without a store code uses.
- The price is the not-logged-in price. Customer-group prices, tier prices and customer-specific catalogue price rules are
  not applied yet. Fine for B2C; revisit for B2B.
- The order endpoint will check stock and price again; this lookup is a guide for the conversation, not a reservation.

## Try it

Do not paste the token into a chat or a shell history. In Git Bash or WSL, wherever `https://magento.test` works:

```bash
read -rs TOKEN; export TOKEN          # paste the Access Token, press Enter (nothing is echoed)
curl -sk -X POST https://magento.test/rest/V1/whatsapp/products/lookup \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"skus":["<a real simple SKU>","nope-123"]}'
```

`-k` skips certificate checking and is for the local Warden certificate only.

| # | Try | Expect |
|---|---|---|
| 1 | A real, enabled, in-stock simple SKU | `found` and `orderable` true, no `reason`, name, price, quantity |
| 2 | `nope-123` | `found` false, `orderable` false |
| 3 | The real SKU in lower case | Same product; `sku` shows Magento's own spelling |
| 4 | Disable the product in the admin, flush cache, ask again | `reason: "disabled"` |
| 5 | Set the product Out of Stock (or quantity 0), ask again | `reason: "out_of_stock"` |
| 6 | The SKU of a configurable product (if you have one) | `reason: "unsupported_type"` |
| 7 | 21 SKUs, then `{"skus":[]}` | HTTP 400 both times |
| 8 | Untick the permission on the Integration | 401 "consumer isn't authorized" (tick it again afterwards) |

Put test products back the way they were.

### If every product looks out of stock

Inventory keeps a separate stock index. If indexers are on "Update by Schedule" and nothing runs cron (typical on a local
Warden store), new or changed products keep a salable quantity of 0. Run `bin/magento indexer:set-mode realtime` and
`bin/magento indexer:reindex`, then `bin/magento cache:flush`.
