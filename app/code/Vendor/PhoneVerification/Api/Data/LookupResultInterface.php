<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Api\Data;

/**
 * @api
 */
interface LookupResultInterface
{
    /**
     * @return bool
     */
    public function getFound();

    /**
     * @param bool $found
     * @return $this
     */
    public function setFound($found);

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

    /**
     * @return bool
     */
    public function getPhoneVerified();

    /**
     * @param bool $phoneVerified
     * @return $this
     */
    public function setPhoneVerified($phoneVerified);
}
