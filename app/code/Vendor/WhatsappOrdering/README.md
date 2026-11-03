# Vendor_WhatsappOrdering

Catalogue and order endpoints for the WhatsApp ordering bot (`chat-commerce-bot`). It is separate from
`Vendor_PhoneVerification` on purpose: that module knows who a customer is, this one knows what can be ordered, and each
has its own permissions on the Magento Integration.

**Status (v3):** SKU lookup is built and tested. The **order preview** and the checkout settings are new in v3 and have
not been run yet (see "What was not verified"). Placing the order, categories, search and frequent products come later.

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
   This module has no database changes. `app/etc/config.php` gains one line; commit it (upgrading from v1/v2: it is
   already there, so `setup:upgrade` and `cache:flush` are enough). `Vendor_PhoneVerification` must be installed too.
3. **System > Extensions > Integrations**, edit the Integration the bot uses, open the **API** tab and tick
   **WhatsApp Ordering > Look up products by SKU** and **Preview an order (address, shipping, totals)**. Save. The Access
   Token does not change.
4. **Stores > Configuration > Sales > WhatsApp Ordering > Checkout from WhatsApp** (see Settings below). Defaults work
   for a first test, except that the shipping code must match a method your store really offers.

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

## Settings (Stores > Configuration > Sales > WhatsApp Ordering)

| Setting | Default | Meaning |
|---|---|---|
| Shipping method code | `flatrate_flatrate` | The one method used for every WhatsApp order, written `carrier_method`. The preview lists the codes available for a customer's address in `available_shipping`; copy one from there. |
| Payment method code | `cashondelivery` | Must be enabled under Sales > Payment Methods. |
| Maximum order value | `5000` | Largest grand total accepted, in the store's base currency. `0` = no limit. |
| Maximum items per order | `50` | Largest total quantity. `0` = no limit. |

Settings are global (default scope only). A cache flush is needed after changing them if the config cache is on.

## Endpoint: `POST /rest/V1/whatsapp/orders/preview`

Permission: `Vendor_WhatsappOrdering::order_preview`. It **creates nothing and reserves no stock**: it builds an unsaved
quote, asks Magento for shipping rates and totals, and describes the result.

Request: the sender's WhatsApp number and 1 to 20 items (SKU as Magento spells it, quantity of at least 1).

```json
{ "phone": "27821234567", "items": [ { "sku": "black-za", "qty": 2 } ] }
```

Response (field names as Magento sends them; fields with no value are left out):

```json
{
  "ok": true,
  "problems": [],
  "lines": [ { "sku": "black-za", "name": "Black Label", "qty": 2, "unit_price": 20, "row_total": 40 } ],
  "delivery_name": "Alex Khumalo",
  "delivery_address": "1 Example Street, Johannesburg, Gauteng, 2000",
  "shipping_code": "flatrate_flatrate",
  "shipping_label": "Flat Rate - Fixed",
  "shipping_amount": 10,
  "subtotal": 40,
  "tax_amount": 0,
  "grand_total": 50,
  "currency": "ZAR",
  "payment_label": "Cash On Delivery",
  "available_shipping": [ { "code": "flatrate_flatrate", "label": "Flat Rate - Fixed", "amount": 10 } ]
}
```

`ok` is true only when `problems` is empty. Problems are sentences meant for the customer: number not verified, no
default delivery address, an item Magento refuses (not found, stock, minimum or maximum quantity), the configured
shipping method not offered for the address, payment method unavailable, or one of the two limits exceeded.
Malformed requests (no items, more than 20, a blank SKU or quantity under 1) give HTTP 400. Totals come from Magento's
own calculation, so tax, rules and rounding match the website.

### Known limits

- Default store view and default shipping address only; the billing address is the default billing address, or the
  shipping address if there is none.
- A cart rule or catalogue price rule for logged-in customers is applied only as far as Magento applies it to a quote
  for this customer; the earlier lookup price is still the not-logged-in price, so the preview total can differ.

### What was not verified

The PHP passes `php -l` and the XML is well formed, but Magento could not be run where this was written. The riskiest
part is totals on an **unsaved** quote. If the first real call fails or returns zero totals, the fix is to save an
inactive quote, collect totals and delete it afterwards; report the response or the line in `var/log/exception.log`.

### Try it (preview)

```bash
curl -sk -X POST https://magento.test/rest/V1/whatsapp/orders/preview \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"phone":"<your verified number>","items":[{"sku":"<a real SKU>","qty":2}]}'
```

| # | Try | Expect |
|---|---|---|
| 1 | Your verified number, one real in-stock SKU, qty 2 | `ok` true, line, address, shipping, totals; `available_shipping` lists real codes |
| 2 | Same, but set the shipping code to one from `available_shipping` and flush cache | `shipping_code` follows it |
| 3 | A number that is not in Magento, or one not verified | `ok` false, "This number is not verified yet." |
| 4 | A customer with no default shipping address | `ok` false, "There is no default delivery address..." |
| 5 | SKU `nope-123` | `ok` false, "nope-123: this SKU was not found." |
| 6 | Quantity larger than the salable quantity | `ok` false with Magento's stock message |
| 7 | Set Maximum order value to 10, then repeat test 1 | `ok` false, "An order can be at most 10.00 ZAR." (set it back) |
| 8 | Set the shipping code to `nope_nope` | `ok` false, "Delivery to your address is not available right now."; `available_shipping` still listed |
| 9 | `"items": []` | HTTP 400 |
| 10 | Untick the preview permission | 401 "consumer isn't authorized" (tick it again) |
| 11 | After tests 1 to 7, check **Sales > Orders** and **Reports > Shopping Carts** | Nothing new: the preview must not leave a quote or an order |

## Try it (SKU lookup)

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
