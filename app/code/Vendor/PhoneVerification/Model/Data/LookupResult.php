<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model\Data;

use Magento\Framework\DataObject;
use Vendor\PhoneVerification\Api\Data\LookupResultInterface;

class LookupResult extends DataObject implements LookupResultInterface
{
    public function getFound()
    {
        return (bool) $this->getData('found');
    }

    public function setFound($found)
    {
        return $this->setData('found', (bool) $found);
    }

    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    public function setCustomerId($customerId)
    {
        return $this->setData('customer_id', $customerId);
    }

    public function getFirstName()
    {
        return $this->getData('first_name');
    }

    public function setFirstName($firstName)
    {
        return $this->setData('first_name', $firstName);
    }

    public function getPhoneVerified()
    {
        return (bool) $this->getData('phone_verified');
    }

    public function setPhoneVerified($phoneVerified)
    {
        return $this->setData('phone_verified', (bool) $phoneVerified);
    }
}
