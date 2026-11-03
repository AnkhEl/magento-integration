<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/** Admin settings: Stores > Configuration > Sales > WhatsApp Ordering. */
class Config
{
    private const PATH = 'whatsapp_ordering/checkout/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getShippingMethod(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . 'shipping_method'));
    }

    public function getPaymentMethod(): string
    {
        return trim((string) $this->scopeConfig->getValue(self::PATH . 'payment_method'));
    }

    /** 0 means no limit. */
    public function getMaxOrderValue(): float
    {
        return max(0.0, (float) $this->scopeConfig->getValue(self::PATH . 'max_order_value'));
    }

    /** 0 means no limit. */
    public function getMaxItems(): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::PATH . 'max_items'));
    }
}
