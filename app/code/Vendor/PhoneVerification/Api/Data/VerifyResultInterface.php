<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Api\Data;

/**
 * @api
 */
interface VerifyResultInterface
{
    /**
     * @return bool
     */
    public function getSuccess();

    /**
     * @param bool $success
     * @return $this
     */
    public function setSuccess($success);

    /**
     * @return string
     */
    public function getMessage();

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage($message);

    /**
     * @return int|null
     */
    public function getCustomerId();

    /**
     * @param int|null $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * @return string|null
     */
    public function getFirstName();

    /**
     * @param string|null $firstName
     * @return $this
     */
    public function setFirstName($firstName);
}
