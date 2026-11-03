<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * One item as Magento prices it in the order.
 *
 * @api
 */
interface PreviewLineInterface
{
    /**
     * SKU
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
     * Product name
     *
     * @return string|null
     */
    public function getName();

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName($name);

    /**
     * Quantity
     *
     * @return int
     */
    public function getQty();

    /**
     * @param int $qty
     * @return $this
     */
    public function setQty($qty);

    /**
     * Price of one, as the customer pays it
     *
     * @return float|null
     */
    public function getUnitPrice();

    /**
     * @param float|null $unitPrice
     * @return $this
     */
    public function setUnitPrice($unitPrice);

    /**
     * Price of the whole line
     *
     * @return float|null
     */
    public function getRowTotal();

    /**
     * @param float|null $rowTotal
     * @return $this
     */
    public function setRowTotal($rowTotal);
}
