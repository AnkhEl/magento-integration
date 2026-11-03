<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * A shipping method Magento offers for the delivery address (shown so the right code can be configured).
 *
 * @api
 */
interface ShippingOptionInterface
{
    /**
     * carrier_method, for example flatrate_flatrate
     *
     * @return string
     */
    public function getCode();

    /**
     * @param string $code
     * @return $this
     */
    public function setCode($code);

    /**
     * Carrier and method title
     *
     * @return string
     */
    public function getLabel();

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel($label);

    /**
     * Price of this method
     *
     * @return float|null
     */
    public function getAmount();

    /**
     * @param float|null $amount
     * @return $this
     */
    public function setAmount($amount);
}
