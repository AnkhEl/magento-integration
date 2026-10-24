<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

use Magento\Framework\Exception\LocalizedException;

class PhoneNumber
{
    public function normalize(string $input): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($input));
        if (!is_string($phone) || !preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            throw new LocalizedException(__('Enter a phone number with country code, such as +27821234567.'));
        }
        return $phone;
    }
}
