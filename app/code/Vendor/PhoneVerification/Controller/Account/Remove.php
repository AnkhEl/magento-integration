<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Vendor\PhoneVerification\Model\PendingCodeStore;
use Vendor\PhoneVerification\Model\VerificationService;

/**
 * AJAX: removes the number from the account and invalidates any pending code.
 */
class Remove extends AbstractAccount implements HttpPostActionInterface
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
            $this->service->removePhone((int) $this->customerSession->getCustomerId());
            $this->pendingCodeStore->clear();

            return $result->setData(['success' => true]);
        } catch (\Throwable $e) {
            $this->logger->error('Phone removal failed: ' . $e->getMessage(), ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Something went wrong. Please try again.'),
            ]);
        }
    }
}
