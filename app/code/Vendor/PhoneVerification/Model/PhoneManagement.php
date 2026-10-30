<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Vendor\PhoneVerification\Api\Data\LookupResultInterface;
use Vendor\PhoneVerification\Api\Data\LookupResultInterfaceFactory;
use Vendor\PhoneVerification\Api\Data\VerifyResultInterface;
use Vendor\PhoneVerification\Api\Data\VerifyResultInterfaceFactory;
use Vendor\PhoneVerification\Api\PhoneManagementInterface;

class PhoneManagement implements PhoneManagementInterface
{
    public function __construct(
        private readonly VerificationService $service,
        private readonly VerifyResultInterfaceFactory $verifyResultFactory,
        private readonly LookupResultInterfaceFactory $lookupResultFactory
    ) {
    }

    public function verify($phone, $code)
    {
        $result = $this->service->verifyFromChannel((string) $phone, (string) $code);

        /** @var VerifyResultInterface $dto */
        $dto = $this->verifyResultFactory->create();
        $dto->setSuccess($result['success']);
        $dto->setMessage($result['message']);
        $dto->setCustomerId($result['customer_id']);
        $dto->setFirstName($result['first_name']);

        return $dto;
    }

    public function lookup($phone)
    {
        $result = $this->service->lookup((string) $phone);

        /** @var LookupResultInterface $dto */
        $dto = $this->lookupResultFactory->create();
        $dto->setFound($result['found']);
        $dto->setCustomerId($result['customer_id']);
        $dto->setFirstName($result['first_name']);
        $dto->setPhoneVerified($result['phone_verified']);

        return $dto;
    }
}
