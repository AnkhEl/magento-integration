<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\OrderItemInterface;

class OrderItem extends DataObject implements OrderItemInterface
{
    public function getSku()
    {
        return $this->getData('sku');
    }

    public function setSku($sku)
    {
        return $this->setData('sku', $sku);
    }

    public function getQty()
    {
        return $this->getData('qty');
    }

    public function setQty($qty)
    {
        return $this->setData('qty', $qty);
    }
}
