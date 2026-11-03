<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\ShippingOptionInterface;

class ShippingOption extends DataObject implements ShippingOptionInterface
{
    public function getCode()
    {
        return $this->getData('code');
    }

    public function setCode($code)
    {
        return $this->setData('code', $code);
    }

    public function getLabel()
    {
        return $this->getData('label');
    }

    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }

    public function getAmount()
    {
        return $this->getData('amount');
    }

    public function setAmount($amount)
    {
        return $this->setData('amount', $amount);
    }
}
