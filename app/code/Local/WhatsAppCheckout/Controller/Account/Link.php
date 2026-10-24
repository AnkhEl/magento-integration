<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

class Link implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        return $this->pageFactory->create();
    }
}
