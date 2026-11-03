<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * One item of the cart sent for a preview or an order.
 *
 * @api
 */
interface OrderItemInterface
{
    /**
     * SKU as Magento spells it
     *
     * @return string
     */
    public function getSku();

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku);

    /**
     * How many, at least 1
     *
     * @return int
     */
    public function getQty();

    /**
     * @param int $qty
     * @return $this
     */
    public function setQty($qty);
}
