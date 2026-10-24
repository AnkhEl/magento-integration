<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Block\Account;

use Local\WhatsAppCheckout\Model\Storage;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Link extends Template
{
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Storage $storage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRegisteredPhone(): string
    {
        $customerId = $this->customerSession->getCustomerId();
        if ($customerId === null) {
            return '';
        }
        $customer = $this->customerRepository->getById((int) $customerId);
        $attribute = $customer->getCustomAttribute('phone_number');
        return $attribute ? (string) $attribute->getValue() : '';
    }

    public function getLinkedPhone(): ?string
    {
        $customerId = $this->customerSession->getCustomerId();
        return $customerId === null ? null : $this->storage->linkedPhone((int) $customerId);
    }
}
