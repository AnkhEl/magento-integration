<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\ProductInfoInterface;

class ProductInfo extends DataObject implements ProductInfoInterface
{
    public function getRequestedSku()
    {
        return (string) $this->getData('requested_sku');
    }

    public function setRequestedSku($requestedSku)
    {
        return $this->setData('requested_sku', $requestedSku);
    }

    public function getFound()
    {
        return (bool) $this->getData('found');
    }

    public function setFound($found)
    {
        return $this->setData('found', (bool) $found);
    }

    public function getOrderable()
    {
        return (bool) $this->getData('orderable');
    }

    public function setOrderable($orderable)
    {
        return $this->setData('orderable', (bool) $orderable);
    }

    public function getReason()
    {
        return $this->getData('reason');
    }

    public function setReason($reason)
    {
        return $this->setData('reason', $reason);
    }

    public function getSku()
    {
        return $this->getData('sku');
    }

    public function setSku($sku)
    {
        return $this->setData('sku', $sku);
    }

    public function getName()
    {
        return $this->getData('name');
    }

    public function setName($name)
    {
        return $this->setData('name', $name);
    }

    public function getPrice()
    {
        return $this->getData('price');
    }

    public function setPrice($price)
    {
        return $this->setData('price', $price);
    }

    public function getCurrency()
    {
        return $this->getData('currency');
    }

    public function setCurrency($currency)
    {
        return $this->setData('currency', $currency);
    }

    public function getSalableQty()
    {
        return $this->getData('salable_qty');
    }

    public function setSalableQty($salableQty)
    {
        return $this->setData('salable_qty', $salableQty);
    }
}
