# Vendor_PhoneVerification

Customer-level phone number + ownership verification for Magento 2 Open Source.
A customer adds their number under **My Account > Phone & WhatsApp**, receives a one-time code, and
proves they control the number by sending `VERIFY <code>` to your WhatsApp business number.
The messaging middleware (Spring Boot) completes verification through a REST endpoint.

## Install (Warden)

Unzip at the Magento project root so the files land in `app/code/Vendor/PhoneVerification`, then:

```bash
warden shell
bin/magento module:enable Vendor_PhoneVerification
bin/magento setup:upgrade
bin/magento cache:flush
# production mode only:
# bin/magento setup:di:compile && bin/magento setup:static-content:deploy -f
```

To rename the vendor namespace, find/replace `Vendor` (directory, `namespace`, XML class names, `Vendor_PhoneVerification::` template/ACL references).

## Configure

Stores > Configuration > Customers > **Phone Verification**

| Setting | Notes |
|---|---|
| Enable phone verification | master switch |
| WhatsApp sender number | digits only, e.g. `27821234567`. The wa.me link and "send to" target. Verification is unavailable until set. |
| Default country calling code | `27`. Applied to local numbers starting with 0. |
| Code validity / max attempts / cooldown | defaults 10 min / 5 / 30 s |

## What it adds

- `customer_entity.phone_number` (E.164, unique), `phone_verified`, `phone_verified_at`
- `phone_verification` table (stores only an HMAC hash of the code, bound to the phone number)
- Admin: phone number and verified flag on the customer edit form
- Storefront: `/phone/account/` page + sidebar link (AJAX request, live status polling, remove number).
  The plain code is kept only in the customer's server-side session (the database stores hashes only), so a page
  reload shows the same code and countdown again while it is still valid. The page block is `cacheable="false"`
  so full-page cache can never store a customer's number or code.
- REST (Integration token): `POST /V1/phone/verify`, `GET /V1/phone/lookup`

## Middleware integration

0. **Allow integration tokens as bearer tokens** (Magento 2.4.4+ blocks this by default; without it every call fails with
   "The consumer isn't authorized to access Vendor_PhoneVerification::verify" even with a correct token):
   Stores > Configuration > Services > OAuth > Consumer Settings > *Allow OAuth Access Tokens to be used as
   standalone Bearer tokens* = Yes, or `bin/magento config:set oauth/consumer/enable_integration_as_bearer 1`
   then `bin/magento cache:flush`.
1. System > Extensions > Integrations > Add. On the **API** tab tick only
   *Phone Verification > Verify phone via messaging channel* and *Look up customer by phone*.
   Activate and copy the **Access Token** (not the Access Token Secret).
2. Verify (called when the webhook receives `VERIFY 482913` from `27821234567`):

```bash
curl -X POST https://magento.test/rest/V1/phone/verify \
  -H "Authorization: Bearer <ACCESS_TOKEN>" -H "Content-Type: application/json" \
  -d '{"phone":"27821234567","code":"482913"}'
# {"success":true,"message":"Your number is verified.","customer_id":12,"first_name":"Thabo"}
# failure (any reason): {"success":false,"message":"That code is invalid or has expired."}
```

3. Lookup (gate for the ordering flow). Send digits only, or URL-encode the `+` as `%2B`:

```bash
curl "https://magento.test/rest/V1/phone/lookup?phone=27821234567" -H "Authorization: Bearer <ACCESS_TOKEN>"
# {"found":true,"customer_id":12,"first_name":"Thabo","phone_verified":true}
```

## Manual test matrix

1. Log in, open My Account > Phone & WhatsApp, enter `082 123 4567`: page shows `VERIFY ######` and a countdown.
2. Send the code from that WhatsApp number (or call the verify endpoint with curl): page flips to *Verified*.
3. Wrong code: generic failure; after 5 wrong attempts the code is invalidated.
4. Set *Code validity* to 1 minute: page reports expiry and shows the form again.
5. Same number on a second account: rejected with a generic message.
6. Change the number in admin: `phone_verified` resets to 0 (unless you change the flag in the same save).
7. Verify with a number that has no pending code: generic failure (same as a wrong code).

## Known limitations / next steps

- **Number squatting:** because the number is unique per customer, someone can park a number they do not own
  (unverified) and block its real owner. Mitigation if it matters: add a nullable `verified_phone` column with the
  unique index instead, and only enforce uniqueness for verified numbers.
- **Customer grid:** the new columns are not added to the admin customer grid.
- **Customer sharing scope:** lookups are global. If you run several websites with per-website customer accounts,
  add a website filter.
- **Number parsing:** a lightweight normalizer is used. For worldwide validation swap its body for
  `giggsey/libphonenumber-for-php`.
- **Rate limiting inbound `VERIFY` messages** belongs in the middleware (e.g. 1 reply/minute per unknown number).
