<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterface;

class OrderPreview extends DataObject implements OrderPreviewInterface
{
    public function getOk()
    {
        return (bool) $this->getData('ok');
    }

    public function setOk($ok)
    {
        return $this->setData('ok', (bool) $ok);
    }

    public function getProblems()
    {
        return (array) $this->getData('problems');
    }

    public function setProblems($problems)
    {
        return $this->setData('problems', $problems);
    }

    public function getLines()
    {
        return (array) $this->getData('lines');
    }

    public function setLines($lines)
    {
        return $this->setData('lines', $lines);
    }

    public function getDeliveryName()
    {
        return $this->getData('delivery_name');
    }

    public function setDeliveryName($deliveryName)
    {
        return $this->setData('delivery_name', $deliveryName);
    }

    public function getDeliveryAddress()
    {
        return $this->getData('delivery_address');
    }

    public function setDeliveryAddress($deliveryAddress)
    {
        return $this->setData('delivery_address', $deliveryAddress);
    }

    public function getShippingCode()
    {
        return $this->getData('shipping_code');
    }

    public function setShippingCode($shippingCode)
    {
        return $this->setData('shipping_code', $shippingCode);
    }

    public function getShippingLabel()
    {
        return $this->getData('shipping_label');
    }

    public function setShippingLabel($shippingLabel)
    {
        return $this->setData('shipping_label', $shippingLabel);
    }

    public function getShippingAmount()
    {
        return $this->getData('shipping_amount');
    }

    public function setShippingAmount($shippingAmount)
    {
        return $this->setData('shipping_amount', $shippingAmount);
    }

    public function getSubtotal()
    {
        return $this->getData('subtotal');
    }

    public function setSubtotal($subtotal)
    {
        return $this->setData('subtotal', $subtotal);
    }

    public function getTaxAmount()
    {
        return $this->getData('tax_amount');
    }

    public function setTaxAmount($taxAmount)
    {
        return $this->setData('tax_amount', $taxAmount);
    }

    public function getGrandTotal()
    {
        return $this->getData('grand_total');
    }

    public function setGrandTotal($grandTotal)
    {
        return $this->setData('grand_total', $grandTotal);
    }

    public function getCurrency()
    {
        return $this->getData('currency');
    }

    public function setCurrency($currency)
    {
        return $this->setData('currency', $currency);
    }

    public function getPaymentLabel()
    {
        return $this->getData('payment_label');
    }

    public function setPaymentLabel($paymentLabel)
    {
        return $this->setData('payment_label', $paymentLabel);
    }

    public function getAvailableShipping()
    {
        return (array) $this->getData('available_shipping');
    }

    public function setAvailableShipping($availableShipping)
    {
        return $this->setData('available_shipping', $availableShipping);
    }
}
