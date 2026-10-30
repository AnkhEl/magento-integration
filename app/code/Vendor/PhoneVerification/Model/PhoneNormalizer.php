<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Converts free-text phone numbers into E.164 (+<country><subscriber>).
 *
 * Handles: "082 123 4567", "+27 82 123 4567", "0027821234567", "27821234567" (as sent by WhatsApp
 * webhooks), "+27 (0)82 123 4567". For production-grade validation of every country you can swap
 * the body of normalize() for giggsey/libphonenumber-for-php without changing callers.
 */
class PhoneNormalizer
{
    private const E164_PATTERN = '/^\+[1-9]\d{7,14}$/';

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @return string|null E.164 number, or null when the input is empty
     * @throws LocalizedException when the input is not a plausible phone number
     */
    public function normalize(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        // Only digits, spaces and common separators are allowed in the input.
        if (!preg_match('/^\+?[\d\s().\-]+$/', $raw)) {
            throw new LocalizedException(__('Please enter a valid phone number.'));
        }

        $international = str_starts_with($raw, '+');
        $digits = (string) preg_replace('/\D+/', '', $raw);
        $cc = $this->config->getDefaultCountryCode();

        if (!$international) {
            if (str_starts_with($digits, '00')) {
                // 0027821234567 -> 27821234567
                $digits = substr($digits, 2);
            } elseif (str_starts_with($digits, '0')) {
                // 0821234567 -> <cc>821234567
                $digits = $cc . substr($digits, 1);
            }
            // Otherwise assume it already carries a country code (WhatsApp sends 27821234567).
        }

        // "+27 (0)82 ..." -> drop the redundant trunk zero after the default country code.
        if (str_starts_with($digits, $cc . '0')) {
            $digits = $cc . substr($digits, strlen($cc) + 1);
        }

        $e164 = '+' . $digits;
        if (!preg_match(self::E164_PATTERN, $e164)) {
            throw new LocalizedException(__('Please enter a valid phone number.'));
        }

        return $e164;
    }

    /** Same as normalize() but returns null instead of throwing (for inbound webhook data). */
    public function tryNormalize(?string $raw): ?string
    {
        try {
            return $this->normalize($raw);
        } catch (LocalizedException) {
            return null;
        }
    }
}
