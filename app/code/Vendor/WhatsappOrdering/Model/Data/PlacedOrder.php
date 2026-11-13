<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model\Data;

use Magento\Framework\DataObject;
use Vendor\WhatsappOrdering\Api\Data\PlacedOrderInterface;

class PlacedOrder extends DataObject implements PlacedOrderInterface
{
    public function getOk()
    {
        return (bool) $this->getData('ok');
    }

    public function setOk($ok)
    {
        return $this->setData('ok', $ok);
    }

    public function getProblems()
    {
        return (array) $this->getData('problems');
    }

    public function setProblems($problems)
    {
        return $this->setData('problems', $problems);
    }

    public function getOrderNumber()
    {
        return $this->getData('order_number');
    }

    public function setOrderNumber($orderNumber)
    {
        return $this->setData('order_number', $orderNumber);
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

    public function getAlreadyPlaced()
    {
        return (bool) $this->getData('already_placed');
    }

    public function setAlreadyPlaced($alreadyPlaced)
    {
        return $this->setData('already_placed', $alreadyPlaced);
    }
}
