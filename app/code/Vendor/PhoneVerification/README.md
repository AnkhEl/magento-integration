# Vendor_PhoneVerification

Customer phone numbers for Magento 2 Open Source, with ownership verification over WhatsApp.

A customer adds their number under **My Account > Phone & WhatsApp** and receives a one-time code. They prove they
control the number by sending `VERIFY <code>` to the store's WhatsApp number. A messaging middleware (the
`chat-commerce-bot` Spring Boot service) receives that message and completes verification through a REST endpoint.

The number is stored on the customer, in E.164 format, and is deliberately general purpose: WhatsApp ordering uses
it today, SMS notifications or SMS login can use it later.

## Features

- `phone_number` (E.164, unique), `phone_verified` and `phone_verified_at` as real columns on `customer_entity`
- Storefront page with live status: enter a number, see the code and a countdown, an "Open WhatsApp" button
  (`wa.me` link with the message prefilled), and **Remove number**
- The code survives a page reload (kept in the customer's session, never in the database)
- REST endpoints for the middleware: `verify` and `lookup`, protected by their own ACL resources
- Admin: phone number and verified flag on the customer edit form; configuration section for the sender number,
  default country code, code lifetime, attempt limit and cooldown
- Numbers are normalised on every save (admin, API, imports); duplicates are rejected; changing a number resets
  the verified flag

## Requirements

- Magento 2.4.4 or newer (the bearer-token setting below was introduced in 2.4.4)
- Developed and tested on Warden with PHP 8.4, MariaDB 11.8 and Valkey (Redis) sessions and cache

## Installation (Warden)

Copy the module to `app/code/Vendor/PhoneVerification`, then in `warden shell`:

```bash
bin/magento setup:upgrade      # also enables a new app/code module
bin/magento cache:flush
```

In **production mode** also compile and deploy static content:

```bash
bin/magento setup:di:compile && bin/magento setup:static-content:deploy -f
```

The first CLI command after clearing `generated/` can take a few minutes while Magento rebuilds generated code. Let it
finish; interrupting it is what causes half-deleted `generated/` folders.

**Renaming the vendor:** find and replace `Vendor` with your vendor name in the folder path, every `namespace` and
`use`, the XML class names, and the `Vendor_PhoneVerification::` references in the layout, template and ACL.

**If you had an older `phone_number` EAV attribute** (for example from an earlier custom module), remove it first. This
module registers `phone_number` as a *static* attribute backed by a real column, and two definitions of the same
attribute silently disagree. Disable the old module, delete the old attribute (`DELETE FROM eav_attribute WHERE
attribute_code='phone_number' AND entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE
entity_type_code='customer')`), back up the database first, then run `setup:upgrade`.

## Configuration

**Stores > Configuration > Customers > Phone Verification**

| Setting | Default | Notes |
|---|---|---|
| Enable phone verification | Yes | Master switch |
| WhatsApp sender number | empty | Digits only, e.g. `27821234567` (no `+`). Used for the `wa.me` link. **The feature stays unavailable until this is set.** |
| Default country calling code | `27` | Applied to local numbers starting with `0` |
| Code validity (minutes) | `10` | |
| Maximum attempts per code | `5` | After that the code is invalidated |
| Cooldown between code requests (seconds) | `30` | |

Run `bin/magento cache:flush` after changing settings.

## Connecting the middleware

1. **Allow integration tokens as bearer tokens.** Since Magento 2.4.4 this is off by default. While it is off, every
   call fails with *"The consumer isn't authorized to access Vendor_PhoneVerification::verify"* even when the token and
   permissions are correct, because Magento treats the request as an anonymous guest.
   **Stores > Configuration > Services > OAuth > Consumer Settings > Allow OAuth Access Tokens to be used as
   standalone Bearer tokens = Yes**, or:
   ```bash
   bin/magento config:set oauth/consumer/enable_integration_as_bearer 1
   bin/magento cache:flush
   ```
2. **Create the Integration.** *System > Extensions > Integrations > Add New Integration*. On the **API** tab set
   Resource Access to **Custom** and tick only *Phone Verification > Verify phone via messaging channel* and
   *Look up customer by phone*. Save, **Activate**, **Allow**.
3. **Copy the Access Token** (not the Access Token Secret, Consumer Key or Consumer Secret) into the middleware's
   `MAGENTO_TOKEN`. Treat it like a password. *Reauthorize* issues a new one if it leaks.

If *Phone Verification* is missing from the permissions tree, flush the cache and reload.

## REST API

Both endpoints require `Authorization: Bearer <access token>`. Phone numbers are accepted in any common format
(`27821234567` as sent by WhatsApp, `+27821234567`, `082 123 4567`) and normalised.

### `POST /rest/V1/phone/verify`

Called when a customer sends `VERIFY <code>` from WhatsApp. It never returns an error for a bad code, so callers cannot
tell a wrong code from an expired one or an unknown number.

```bash
curl -X POST https://magento.test/rest/V1/phone/verify \
  -H "Authorization: Bearer <ACCESS_TOKEN>" -H "Content-Type: application/json" \
  -d '{"phone":"27821234567","code":"482913"}'
```

```json
{"success":true,"message":"Your number is verified.","customer_id":2,"first_name":"Alex"}
{"success":false,"message":"That code is invalid or has expired.","customer_id":null,"first_name":null}
```

### `GET /rest/V1/phone/lookup?phone=27821234567`

Finds the customer that holds a number (use digits, or encode `+` as `%2B`).

```json
{"found":true,"customer_id":2,"first_name":"Alex","phone_verified":true}
{"found":false,"customer_id":null,"first_name":null,"phone_verified":false}
```

For local testing against Warden's own certificate, add `-k` to curl. Never do that outside development.

## How verification works

1. The customer submits a number. `VerificationService::requestCode` checks the rules (enabled, sender configured,
   cooldown, number not held by another customer), saves the number as **unverified**, cancels any earlier code, and
   creates a random 6-digit code.
2. Only an **HMAC-SHA256 hash** of the code is stored, keyed with Magento's crypt key and bound to the phone number.
   The plain code is returned once for display and kept in the customer's server-side session so a reload can show it again.
3. The page polls `phone/account/status` every 3 seconds.
4. `verifyFromChannel` finds the pending code for the **sender's number**, counts the attempt first, compares hashes
   in constant time, confirms the account still holds that number, and in one transaction marks the code consumed and
   sets `phone_verified = 1`.

Security properties: codes are bound to the number, expire (10 minutes by default), allow a limited number of attempts
and work once; every failure looks identical; the database never holds a usable code; the account page is
`cacheable="false"` so full-page cache can never store a customer's number or code.

## Data model

| Where | What |
|---|---|
| `customer_entity.phone_number` | `varchar(20)`, nullable, **unique** (E.164, e.g. `+27821234567`) |
| `customer_entity.phone_verified` | `smallint`, default 0 |
| `customer_entity.phone_verified_at` | UTC datetime string, nullable |
| `phone_verification` | `verification_id`, `customer_id` (FK, cascade delete), `phone`, `code_hash`, `attempts`, `created_at`, `expires_at`, `consumed_at` |

The verified flag is written with plain SQL on purpose, so ordinary customer saves cannot flip it by accident.

## Testing checklist

1. Log in, open **My Account > Phone & WhatsApp**, enter a number: the page shows `VERIFY ######` and a countdown.
2. Reload the page: the same code and a running countdown appear.
3. Call the verify endpoint with curl (or send the code from WhatsApp via the middleware): the page flips to *Verified*
   within a few seconds.
4. Use the same code again, and a made-up code: both return the same generic failure. After 5 wrong attempts the
   pending code is invalidated and a new one must be requested.
5. Set code validity to 1 minute: the page reports expiry and shows the form again.
6. Enter a number that another customer holds: rejected with a generic message.
7. Change the number in admin: `phone_verified` resets to 0 (unless you change the flag in the same save).
8. Remove the number on the page, then call lookup: `found` is false.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| *The consumer isn't authorized to access Vendor_PhoneVerification::verify* | Bearer tokens not allowed (step 1 above), token wrong, Integration not active, or its two permissions not ticked. |
| Page says "Phone verification is not available right now" | Module disabled, or the WhatsApp sender number is not configured. |
| Sidebar link or page missing | Flush the cache; confirm the module is enabled (`bin/magento module:status Vendor_PhoneVerification`). |
| `bin/magento` commands take minutes right after clearing `generated/` | Normal once. Magento regenerates code and compiled config. Do not interrupt. |
| `module:disable` fails with "directory not empty" in `generated/` | Something was writing there, or files are owned by another user. Check with `find generated var -not -user www-data` **inside** `warden shell`, fix ownership as root if needed, then clear `generated/code/*`. |
| All CLI commands hang | Cache or session backend unreachable. Check the service name in `app/etc/env.php` against `warden env ps` (Warden may call it `valkey`, not `redis`). |
| Customer save fails with a phone error | The number is invalid, or another customer already holds it. |

## Known limitations

- **Number squatting.** `phone_number` is unique per customer, so someone can park a number they do not own (it stays
  unverified) and block its real owner. If that matters, add a separate verified-number column and enforce
  uniqueness only on verified numbers.
- The new columns are not added to the admin customer grid yet.
- Lookups are global. With several websites and per-website customer accounts, add a website filter.
- The number normaliser is lightweight. For worldwide validation, put `giggsey/libphonenumber-for-php` inside
  `PhoneNormalizer::normalize()`; callers do not change.
- Rate limiting of inbound `VERIFY` messages belongs in the middleware.
- The module is installed from `app/code`; there is no automatic uninstall. Disabling it leaves the columns and the
  `phone_verification` table in place.
