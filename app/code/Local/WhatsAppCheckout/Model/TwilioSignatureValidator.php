<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

class TwilioSignatureValidator
{
    public function isValid(string $url, array $parameters, string $signature, string $authToken): bool
    {
        if ($url === '' || $signature === '' || $authToken === '') {
            return false;
        }

        ksort($parameters, SORT_STRING);
        $signed = $url;
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                return false;
            }
            $signed .= $name . (string) $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $signed, $authToken, true));
        return hash_equals($expected, $signature);
    }
}
