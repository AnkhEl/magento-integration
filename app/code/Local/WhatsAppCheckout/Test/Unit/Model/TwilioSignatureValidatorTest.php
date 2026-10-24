<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Test\Unit\Model;

use Local\WhatsAppCheckout\Model\TwilioSignatureValidator;
use PHPUnit\Framework\TestCase;

class TwilioSignatureValidatorTest extends TestCase
{
    public function testAcceptsTwilioDocumentedSignature(): void
    {
        $parameters = [
            'To' => '+18005551212',
            'From' => '+14158675310',
            'Digits' => '1234',
            'Caller' => '+14158675310',
            'CallSid' => 'CA1234567890ABCDE',
        ];

        self::assertTrue((new TwilioSignatureValidator())->isValid(
            'https://example.com/myapp.php?foo=1&bar=2',
            $parameters,
            'L/OH5YylLD5NRKLltdqwSvS0BnU=',
            '12345'
        ));
    }

    public function testRejectsChangedMessage(): void
    {
        $parameters = [
            'To' => '+18005551212',
            'From' => '+14158675310',
            'Digits' => '4321',
            'Caller' => '+14158675310',
            'CallSid' => 'CA1234567890ABCDE',
        ];

        self::assertFalse((new TwilioSignatureValidator())->isValid(
            'https://example.com/myapp.php?foo=1&bar=2',
            $parameters,
            'L/OH5YylLD5NRKLltdqwSvS0BnU=',
            '12345'
        ));
    }
}
