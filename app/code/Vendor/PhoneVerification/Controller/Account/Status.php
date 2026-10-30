<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Controller\Account;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Vendor\PhoneVerification\Model\VerificationService;

/**
 * AJAX polling endpoint: tells the page whether the code was consumed (verified) or has expired.
 */
class Status extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly Session $customerSession,
        private readonly JsonFactory $jsonFactory,
        private readonly VerificationService $service
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData(['success' => false]);
        }

        return $result->setData(
            ['success' => true] + $this->service->getStatus((int) $this->customerSession->getCustomerId())
        );
    }
}
