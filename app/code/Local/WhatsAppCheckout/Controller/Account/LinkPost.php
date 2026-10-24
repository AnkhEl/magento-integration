<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Controller\Account;

use Local\WhatsAppCheckout\Model\PhoneNumber;
use Local\WhatsAppCheckout\Model\Storage;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class LinkPost implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RequestInterface $request,
        private readonly Validator $formKeyValidator,
        private readonly PhoneNumber $phoneNumber,
        private readonly Storage $storage,
        private readonly StoreManagerInterface $storeManager,
        private readonly ManagerInterface $messageManager,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Please refresh the page and try again.'));
            return $redirect->setPath('whatsappcheckout/account/link');
        }
        try {
            $phone = $this->phoneNumber->normalize((string) $this->request->getParam('phone_number', ''));
            $code = (string) random_int(100000, 999999);
            $this->storage->startChallenge(
                (int) $this->customerSession->getCustomerId(),
                (int) $this->storeManager->getWebsite()->getId(),
                $phone,
                $code
            );
            $this->messageManager->addSuccessMessage(
                __('From %1, send LINK %2 to our WhatsApp number within 10 minutes.', $phone, $code)
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $redirect->setPath('whatsappcheckout/account/link');
    }
}
