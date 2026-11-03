<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api;

/**
 * Order questions asked by the messaging middleware (protected by Magento Integration tokens).
 *
 * @api
 */
interface OrderPreviewManagementInterface
{
    /**
     * What would this order look like? Uses the verified customer's default shipping address, the configured shipping
     * and payment methods, and Magento's own totals and limits. Nothing is created or reserved.
     * Problems with the order are described in the answer (ok = false), not thrown.
     *
     * @param string $phone Sender number of a verified customer (digits as sent by WhatsApp, or E.164)
     * @param \Vendor\WhatsappOrdering\Api\Data\OrderItemInterface[] $items The cart
     * @return \Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterface
     */
    public function preview($phone, $items);
}
