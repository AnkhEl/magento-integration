<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Core business logic, shared by the My Account page (customer side) and the REST API
 * (middleware side).
 *
 * Flow: the customer enters a number on the website -> requestCode() stores a hashed one-time code
 * bound to that number -> the customer sends "VERIFY <code>" from that number on WhatsApp ->
 * the middleware calls verifyFromChannel() with the sender's number and the code.
 */
class VerificationService
{
    private const GENERIC_FAILURE = 'That code is invalid or has expired.';

    public function __construct(
        private readonly Config $config,
        private readonly PhoneNormalizer $normalizer,
        private readonly CodeHasher $hasher,
        private readonly PhoneStorage $storage,
        private readonly VerificationRepository $repository
    ) {
    }

    /**
     * Starts verification for a logged-in customer.
     *
     * @return array{code: string, phone: string, expires_at: string, expires_in: int, wa_link: string}
     * @throws LocalizedException
     */
    public function requestCode(int $customerId, string $rawPhone): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('Phone verification is currently unavailable.'));
        }

        $sender = $this->config->getWhatsappSender();
        if ($sender === '') {
            throw new LocalizedException(__('WhatsApp verification is not available yet. Please try again later.'));
        }

        $phone = $this->normalizer->normalize($rawPhone);
        if ($phone === null) {
            throw new LocalizedException(__('Please enter your phone number.'));
        }

        $customer = $this->storage->getByCustomerId($customerId);
        if ($customer === null) {
            throw new LocalizedException(__('Customer not found.'));
        }

        if ($customer['phone_number'] === $phone && (int) $customer['phone_verified'] === 1) {
            throw new LocalizedException(__('This number is already verified.'));
        }

        $elapsed = $this->repository->secondsSinceLastRequest($customerId);
        $cooldown = $this->config->getCooldownSeconds();
        if ($elapsed !== null && $elapsed < $cooldown) {
            throw new LocalizedException(
                __('Please wait %1 seconds before requesting a new code.', $cooldown - $elapsed)
            );
        }

        if ($this->storage->isTakenByOther($phone, $customerId)) {
            throw new LocalizedException(
                __('This number cannot be used. Please contact us if you think this is a mistake.')
            );
        }

        $code = (string) random_int(100000, 999999);
        $ttlSeconds = $this->config->getCodeTtlMinutes() * 60;

        $this->repository->beginTransaction();
        try {
            if ($customer['phone_number'] !== $phone) {
                $this->storage->assignPhone($customerId, $phone);
            }
            $this->repository->invalidateForCustomer($customerId);
            $expiresAt = $this->repository->create(
                $customerId,
                $phone,
                $this->hasher->hash($phone, $code),
                $ttlSeconds
            );
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        return [
            'code' => $code,
            'phone' => $phone,
            'expires_at' => $expiresAt,
            'expires_in' => $ttlSeconds,
            'wa_link' => 'https://wa.me/' . $sender . '?text=' . rawurlencode('VERIFY ' . $code),
        ];
    }

    /**
     * Completes verification from the messaging channel.
     * Every failure returns the same message so nothing is revealed about which numbers exist.
     *
     * @return array{success: bool, message: string, customer_id: int|null, first_name: string|null}
     */
    public function verifyFromChannel(string $rawPhone, string $rawCode): array
    {
        $fail = [
            'success' => false,
            'message' => (string) __(self::GENERIC_FAILURE),
            'customer_id' => null,
            'first_name' => null,
        ];

        if (!$this->config->isEnabled()) {
            return $fail;
        }

        $phone = $this->normalizer->tryNormalize($rawPhone);
        $code = (string) preg_replace('/\D+/', '', $rawCode);
        if ($phone === null || strlen($code) !== 6) {
            return $fail;
        }

        $pending = $this->repository->findPendingByPhone($phone);
        if ($pending === null) {
            return $fail;
        }

        $verificationId = (int) $pending['verification_id'];

        // Count the attempt first so parallel guesses cannot exceed the limit.
        $this->repository->incrementAttempts($verificationId);
        $attempts = (int) $pending['attempts'] + 1;
        $maxAttempts = $this->config->getMaxAttempts();

        if ($attempts > $maxAttempts) {
            $this->repository->markConsumed($verificationId);
            return $fail;
        }

        if (!$this->hasher->matches((string) $pending['code_hash'], $phone, $code)) {
            if ($attempts >= $maxAttempts) {
                $this->repository->markConsumed($verificationId);
            }
            return $fail;
        }

        $customerId = (int) $pending['customer_id'];
        $customer = $this->storage->getByCustomerId($customerId);

        // The account must still hold this exact number (it may have been changed or removed).
        if ($customer === null || $customer['phone_number'] !== $phone) {
            $this->repository->markConsumed($verificationId);
            return $fail;
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->markConsumed($verificationId);
            $this->storage->markVerified($customerId);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => (string) __('Your number is verified.'),
            'customer_id' => $customerId,
            'first_name' => (string) $customer['firstname'],
        ];
    }

    /**
     * State used by the My Account page and its polling endpoint.
     *
     * @return array{phone: string|null, verified: bool, pending: bool, expires_in: int}
     */
    public function getStatus(int $customerId): array
    {
        $customer = $this->storage->getByCustomerId($customerId);
        $pending = $this->repository->findPendingByCustomer($customerId);

        $expiresIn = 0;
        if ($pending !== null) {
            $expiresIn = max(0, (int) strtotime($pending['expires_at'] . ' UTC') - time());
        }

        return [
            'phone' => $customer['phone_number'] ?? null,
            'verified' => (int) ($customer['phone_verified'] ?? 0) === 1,
            'pending' => $pending !== null,
            'expires_in' => $expiresIn,
        ];
    }

    public function removePhone(int $customerId): void
    {
        $this->repository->beginTransaction();
        try {
            $this->storage->assignPhone($customerId, null);
            $this->repository->invalidateForCustomer($customerId);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** @return array{found: bool, customer_id: int|null, first_name: string|null, phone_verified: bool} */
    public function lookup(string $rawPhone): array
    {
        $none = ['found' => false, 'customer_id' => null, 'first_name' => null, 'phone_verified' => false];

        $phone = $this->normalizer->tryNormalize($rawPhone);
        if ($phone === null) {
            return $none;
        }

        $row = $this->storage->findByPhone($phone);
        if ($row === null) {
            return $none;
        }

        return [
            'found' => true,
            'customer_id' => (int) $row['entity_id'],
            'first_name' => (string) $row['firstname'],
            'phone_verified' => (int) $row['phone_verified'] === 1,
        ];
    }
}
