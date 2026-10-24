<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Plugin;

use Local\WhatsAppCheckout\Model\PhoneNumber;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\App\RequestInterface;

class RequirePhoneOnRegistration
{
    public function __construct(private readonly PhoneNumber $phoneNumber)
    {
    }

    public function afterExtract(
        CustomerExtractor $subject,
        CustomerInterface $customer,
        string $formCode,
        RequestInterface $request
    ): CustomerInterface {
        if ($formCode === 'customer_account_create') {
            $customer->setCustomAttribute(
                'phone_number',
                $this->phoneNumber->normalize((string) $request->getParam('phone_number', ''))
            );
        }
        return $customer;
    }
}
