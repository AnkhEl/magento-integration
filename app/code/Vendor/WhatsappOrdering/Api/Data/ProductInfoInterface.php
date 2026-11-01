<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * The answer for one SKU in a lookup.
 *
 * @api
 */
interface ProductInfoInterface
{
    /**
     * The SKU exactly as the customer typed it
     *
     * @return string
     */
    public function getRequestedSku();

    /**
     * @param string $requestedSku
     * @return $this
     */
    public function setRequestedSku($requestedSku);

    /**
     * Whether a product with this SKU exists
     *
     * @return bool
     */
    public function getFound();

    /**
     * @param bool $found
     * @return $this
     */
    public function setFound($found);

    /**
     * True only if the product can be ordered right now (reason is then null)
     *
     * @return bool
     */
    public function getOrderable();

    /**
     * @param bool $orderable
     * @return $this
     */
    public function setOrderable($orderable);

    /**
     * Why it cannot be ordered: unsupported_type, disabled, not_on_website, out_of_stock; null when orderable or not found
     *
     * @return string|null
     */
    public function getReason();

    /**
     * @param string|null $reason
     * @return $this
     */
    public function setReason($reason);

    /**
     * The SKU as stored in Magento (letter case may differ from what was typed)
     *
     * @return string|null
     */
    public function getSku();

    /**
     * @param string|null $sku
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
     * Price shown on the storefront to a not-logged-in visitor, in the store base currency
     *
     * @return float|null
     */
    public function getPrice();

    /**
     * @param float|null $price
     * @return $this
     */
    public function setPrice($price);

    /**
     * Currency code of the price
     *
     * @return string|null
     */
    public function getCurrency();

    /**
     * @param string|null $currency
     * @return $this
     */
    public function setCurrency($currency);

    /**
     * Quantity that can be sold now (never negative)
     *
     * @return float|null
     */
    public function getSalableQty();

    /**
     * @param float|null $salableQty
     * @return $this
     */
    public function setSalableQty($salableQty);
}
