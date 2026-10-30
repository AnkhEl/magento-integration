<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Codes are never stored in clear text. The hash is keyed with the store's crypt key and bound to the
 * phone number, so a stolen table cannot be used to brute-force or replay codes for another number.
 */
class CodeHasher
{
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    public function hash(string $phone, string $code): string
    {
        $key = (string) $this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);

        return hash_hmac('sha256', $phone . '|' . $code, $key);
    }

    public function matches(string $storedHash, string $phone, string $code): bool
    {
        return hash_equals($storedHash, $this->hash($phone, $code));
    }
}
