<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api\Data;

/**
 * What an order would look like: where it goes, what it costs, and anything that stops it.
 *
 * @api
 */
interface OrderPreviewInterface
{
    /**
     * True when the order could be placed as previewed (problems is then empty)
     *
     * @return bool
     */
    public function getOk();

    /**
     * @param bool $ok
     * @return $this
     */
    public function setOk($ok);

    /**
     * Reasons the order cannot be placed, written for the customer
     *
     * @return string[]
     */
    public function getProblems();

    /**
     * @param string[] $problems
     * @return $this
     */
    public function setProblems($problems);

    /**
     * The items as Magento prices them
     *
     * @return \Vendor\WhatsappOrdering\Api\Data\PreviewLineInterface[]
     */
    public function getLines();

    /**
     * @param \Vendor\WhatsappOrdering\Api\Data\PreviewLineInterface[] $lines
     * @return $this
     */
    public function setLines($lines);

    /**
     * Name on the delivery address
     *
     * @return string|null
     */
    public function getDeliveryName();

    /**
     * @param string|null $deliveryName
     * @return $this
     */
    public function setDeliveryName($deliveryName);

    /**
     * The default shipping address as one line of text
     *
     * @return string|null
     */
    public function getDeliveryAddress();

    /**
     * @param string|null $deliveryAddress
     * @return $this
     */
    public function setDeliveryAddress($deliveryAddress);

    /**
     * The configured shipping method code
     *
     * @return string|null
     */
    public function getShippingCode();

    /**
     * @param string|null $shippingCode
     * @return $this
     */
    public function setShippingCode($shippingCode);

    /**
     * Title of the shipping method
     *
     * @return string|null
     */
    public function getShippingLabel();

    /**
     * @param string|null $shippingLabel
     * @return $this
     */
    public function setShippingLabel($shippingLabel);

    /**
     * Shipping cost
     *
     * @return float|null
     */
    public function getShippingAmount();

    /**
     * @param float|null $shippingAmount
     * @return $this
     */
    public function setShippingAmount($shippingAmount);

    /**
     * Items before shipping and tax
     *
     * @return float|null
     */
    public function getSubtotal();

    /**
     * @param float|null $subtotal
     * @return $this
     */
    public function setSubtotal($subtotal);

    /**
     * Tax in the order
     *
     * @return float|null
     */
    public function getTaxAmount();

    /**
     * @param float|null $taxAmount
     * @return $this
     */
    public function setTaxAmount($taxAmount);

    /**
     * What the customer pays
     *
     * @return float|null
     */
    public function getGrandTotal();

    /**
     * @param float|null $grandTotal
     * @return $this
     */
    public function setGrandTotal($grandTotal);

    /**
     * Currency code of every amount
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
     * Title of the payment method, for example Cash On Delivery
     *
     * @return string|null
     */
    public function getPaymentLabel();

    /**
     * @param string|null $paymentLabel
     * @return $this
     */
    public function setPaymentLabel($paymentLabel);

    /**
     * Every shipping method Magento offers for the address
     *
     * @return \Vendor\WhatsappOrdering\Api\Data\ShippingOptionInterface[]
     */
    public function getAvailableShipping();

    /**
     * @param \Vendor\WhatsappOrdering\Api\Data\ShippingOptionInterface[] $availableShipping
     * @return $this
     */
    public function setAvailableShipping($availableShipping);
}
