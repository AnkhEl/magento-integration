<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Test\Unit\Model;

use Local\WhatsAppCheckout\Model\PhoneNumber;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function testNormalizesInternationalNumber(): void
    {
        self::assertSame('+27821234567', (new PhoneNumber())->normalize('+27 (82) 123-4567'));
    }

    public function testRejectsMissingCountryCode(): void
    {
        $this->expectException(LocalizedException::class);
        (new PhoneNumber())->normalize('0821234567');
    }
}
