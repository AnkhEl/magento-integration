<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Vendor\PhoneVerification\Model\PendingCodeStore;
use Vendor\PhoneVerification\Model\VerificationService;

/**
 * AJAX: the logged-in customer submits their number and receives a one-time code to send on WhatsApp.
 * The form key is validated by Magento's CSRF validator because this is a plain POST action.
 */
class RequestCode extends AbstractAccount implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly JsonFactory $jsonFactory,
        private readonly VerificationService $service,
        private readonly PendingCodeStore $pendingCodeStore,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([
                'success' => false,
                'message' => (string) __('Please sign in to continue.'),
            ]);
        }

        try {
            $data = $this->service->requestCode(
                (int) $this->customerSession->getCustomerId(),
                (string) $this->getRequest()->getParam('phone')
            );

            // Remember the code in the session so a page reload can show it again.
            $this->pendingCodeStore->save($data['code'], $data['wa_link'], $data['phone'], $data['expires_at']);

            return $result->setData(['success' => true] + $data);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Phone verification request failed: ' . $e->getMessage(), ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Something went wrong. Please try again.'),
            ]);
        }
    }
}
