<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Vendor\PhoneVerification\Model\PhoneNormalizer;
use Vendor\PhoneVerification\Model\PhoneStorage;

/**
 * Runs when a customer is saved (admin edit, API, imports):
 *  - normalizes phone_number to E.164 (empty becomes NULL so the unique index allows many blanks),
 *  - rejects a number already held by another customer,
 *  - resets phone_verified when the number changes, unless the saver changed the flag explicitly.
 */
class NormalizePhoneBeforeCustomerSave implements ObserverInterface
{
    public function __construct(
        private readonly PhoneNormalizer $normalizer,
        private readonly PhoneStorage $storage
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getData('customer') ?? $observer->getEvent()->getData('data_object');

        // Saves that do not carry the phone field (login, password reset, ...) are left alone.
        if (!$customer || !$customer->hasData('phone_number')) {
            return;
        }

        $raw = $customer->getData('phone_number');
        $phone = $this->normalizer->normalize($raw === null ? null : (string) $raw);
        $customer->setData('phone_number', $phone);

        $customerId = (int) $customer->getId();
        $stored = $customerId > 0 ? $this->storage->getByCustomerId($customerId) : null;
        $oldPhone = $stored['phone_number'] ?? null;
        $oldVerified = (int) ($stored['phone_verified'] ?? 0);

        if ($phone !== null && $this->storage->isTakenByOther($phone, $customerId)) {
            throw new LocalizedException(__('This phone number is already linked to another customer.'));
        }

        if ($phone === $oldPhone) {
            return;
        }

        $flagChangedExplicitly = $customer->hasData('phone_verified')
            && (int) $customer->getData('phone_verified') !== $oldVerified;

        if ($phone === null || !$flagChangedExplicitly) {
            $customer->setData('phone_verified', 0);
            $customer->setData('phone_verified_at', null);
        } elseif ((int) $customer->getData('phone_verified') === 1) {
            $customer->setData('phone_verified_at', gmdate('Y-m-d H:i:s'));
        }
    }
}
