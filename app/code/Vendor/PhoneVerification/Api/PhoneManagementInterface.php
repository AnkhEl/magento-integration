<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Api;

/**
 * Endpoints used by the messaging middleware (protected by Magento Integration tokens).
 *
 * @api
 */
interface PhoneManagementInterface
{
    /**
     * Complete verification: the customer sent "VERIFY <code>" from $phone on the messaging channel.
     * Failures never throw; they return success=false with a generic message.
     *
     * @param string $phone Sender number (digits as sent by WhatsApp, or E.164)
     * @param string $code  Six digit code
     * @return \Vendor\PhoneVerification\Api\Data\VerifyResultInterface
     */
    public function verify($phone, $code);

    /**
     * Find the customer that owns a phone number.
     *
     * @param string $phone Sender number (digits as sent by WhatsApp, or E.164)
     * @return \Vendor\PhoneVerification\Api\Data\LookupResultInterface
     */
    public function lookup($phone);
}
