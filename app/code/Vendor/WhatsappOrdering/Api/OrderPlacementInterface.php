<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api;

/**
 * Places the order a customer confirmed in WhatsApp (cash on delivery).
 *
 * @api
 */
interface OrderPlacementInterface
{
    /**
     * Re-checks everything the preview checks (stock, prices, address, shipping, limits), then places the order.
     * Sending the same idempotency key again never creates a second order: it returns the first result.
     * Reasons an order was not placed are returned (ok = false), not thrown; only malformed input is thrown.
     *
     * @param string $phone Sender number of a verified customer
     * @param \Vendor\WhatsappOrdering\Api\Data\OrderItemInterface[] $items The cart
     * @param string $idempotencyKey 8 to 64 characters (letters, digits, . _ : -), unique per confirmation
     * @return \Vendor\WhatsappOrdering\Api\Data\PlacedOrderInterface
     */
    public function place($phone, $items, $idempotencyKey);
}
