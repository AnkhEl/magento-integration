<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model\Data;

use Magento\Framework\DataObject;
use Vendor\PhoneVerification\Api\Data\VerifyResultInterface;

class VerifyResult extends DataObject implements VerifyResultInterface
{
    public function getSuccess()
    {
        return (bool) $this->getData('success');
    }

    public function setSuccess($success)
    {
        return $this->setData('success', (bool) $success);
    }

    public function getMessage()
    {
        return $this->getData('message');
    }

    public function setMessage($message)
    {
        return $this->setData('message', $message);
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
}
