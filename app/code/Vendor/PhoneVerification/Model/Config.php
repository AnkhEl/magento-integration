<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const PATH = 'phoneverification/general/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled');
    }

    /** Sender number, digits only (for wa.me links). Empty string when not configured. */
    public function getWhatsappSender(): string
    {
        return (string) preg_replace('/\D+/', '', (string) $this->scopeConfig->getValue(self::PATH . 'whatsapp_sender'));
    }

    public function getDefaultCountryCode(): string
    {
        $cc = (string) preg_replace('/\D+/', '', (string) $this->scopeConfig->getValue(self::PATH . 'default_country_code'));
        return $cc !== '' ? $cc : '27';
    }

    public function getCodeTtlMinutes(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH . 'code_ttl_minutes'));
    }

    public function getMaxAttempts(): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::PATH . 'max_attempts'));
    }

    public function getCooldownSeconds(): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::PATH . 'cooldown_seconds'));
    }
}
