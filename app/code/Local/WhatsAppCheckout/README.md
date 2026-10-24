# WhatsApp orders through Twilio

This Magento module accepts signed Twilio WhatsApp messages and creates orders
for linked customer accounts. A shopper searches for simple products in chat,
builds a cart of up to 10 product lines, reviews the Magento-calculated total
and saved default shipping address, then sends `CONFIRM`. The order uses
flat-rate shipping and cash on delivery. No Magento browser login is created
from a phone lookup.

## Magento setup

1. Start Docker and the existing Warden environment. Run
   `warden shell -c 'bin/magento module:enable Local_WhatsAppCheckout'`, then
   `warden shell -c 'bin/magento setup:upgrade'`. These commands change the
   application's database; review the target environment first.
2. Enable flat-rate shipping and cash on delivery at the target store scope.
   Configure the applicable countries, shipping amount, and payment limits.
   Create at least one saleable simple product without required options.
3. Give each test customer a default shipping address. If no default billing
   address exists, the shipping address is used for billing.
4. Set a public HTTPS storefront URL. For Warden testing, use an HTTPS tunnel
   and ensure Magento serves the same public host. Twilio must be able to reach
   the webhook. Avoid self-signed certificates.
5. Under **Stores > Configuration > Sales > WhatsApp Checkout > Twilio** at the
   intended store scope, enter the Account SID, Auth Token, WhatsApp Sender
   (`whatsapp:+...`) and exact public webhook URL. Enable the integration after
   these are set. The token is stored encrypted by Magento.

The webhook is `https://<public-storefront>/whatsappcheckout/twilio/inbound`.
Use the store code in that URL if the storefront uses one. Copy the precise URL,
including any query string or trailing slash, into both the Magento setting
and Twilio's incoming message webhook setting. Select HTTP POST. The module
checks Twilio's signature, Account SID and sender before processing a message.

## Link a customer and place a test order

1. Register a new customer with an E.164 phone number, such as `+27821234567`,
   or sign in to an existing customer account. New storefront registrations
   require the phone field; existing customers can add it while linking.
2. Open **My Account > WhatsApp ordering**. Enter the number and generate a
   six-digit code. From that same WhatsApp number, send `LINK <code>` to the
   configured business sender within 10 minutes. Codes have five attempts.
3. Send `SHOP`, then a product name. Reply with the result number and a
   quantity from 1 to 10. Repeat for more products; use `CART` or
   `REMOVE <line number>` to edit the draft.
4. Send `CHECKOUT`. The reply shows the saved shipping address, shipping, tax,
   total and cash-on-delivery payment. Send `CONFIRM` to place the order.
   If the price, availability, address, shipping or tax changes, the module
   asks for a fresh confirmation. `CANCEL` discards a draft.
5. Check the order in Magento Admin. It belongs to the linked customer and
   records Magento's calculated prices and totals.

Unknown WhatsApp numbers receive a link to account registration/sign-in and
number linking. The phone is matched only after a signed webhook and a code
sent from that WhatsApp number. The signed-in browser session remains separate
from the WhatsApp conversation. Message IDs and conversation locks prevent
retries or simultaneous messages from creating the same order twice. Magento
cron removes expired challenges and old conversation/message records daily.

## Twilio testing and limitations

Twilio's legacy WhatsApp Sandbox supports direct dynamic TwiML webhook replies.
The newer **Try out WhatsApp** trial flow does not support direct TwiML XML in
the webhook response, so this module's chat flow requires the legacy Sandbox
or a registered sender on a paid Twilio account. Check current account and
messaging charges in Twilio before using staging or production.

This first version supports simple products without required options, one
store context per configured webhook, the customer's saved default address,
flat-rate shipping and cash on delivery. It does not collect card payment in
WhatsApp. Conversations expire after 30 minutes of inactivity; an order being
placed or completed is kept for seven days for retry recovery.

References: [Twilio Sandbox](https://www.twilio.com/docs/whatsapp/sandbox),
[Twilio trial WhatsApp limitations](https://www.twilio.com/docs/usage/trials/try-out-whatsapp),
[Twilio webhook security](https://www.twilio.com/docs/usage/security),
[Magento cash on delivery](https://experienceleague.adobe.com/en/docs/commerce-admin/stores-sales/payments/offline/cash-on-delivery).
