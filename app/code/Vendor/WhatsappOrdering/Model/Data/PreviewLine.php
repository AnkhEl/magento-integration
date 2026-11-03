<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\PreviewLineInterface;

class PreviewLine extends DataObject implements PreviewLineInterface
{
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

    public function getQty()
    {
        return $this->getData('qty');
    }

    public function setQty($qty)
    {
        return $this->setData('qty', $qty);
    }

    public function getUnitPrice()
    {
        return $this->getData('unit_price');
    }

    public function setUnitPrice($unitPrice)
    {
        return $this->setData('unit_price', $unitPrice);
    }

    public function getRowTotal()
    {
        return $this->getData('row_total');
    }

    public function setRowTotal($rowTotal)
    {
        return $this->setData('row_total', $rowTotal);
    }
}
