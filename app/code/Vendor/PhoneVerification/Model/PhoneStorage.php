<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Direct access to the phone columns on customer_entity.
 *
 * Plain SQL is used on purpose: the verification flow must not trigger customer save events,
 * and it keeps the verified flag out of reach of the normal customer save pipeline.
 */
class PhoneStorage
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /** @return array<string, mixed>|null */
    public function getByCustomerId(int $customerId): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table(), ['entity_id', 'firstname', 'phone_number', 'phone_verified', 'phone_verified_at'])
                ->where('entity_id = ?', $customerId)
        );

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByPhone(string $phone): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table(), ['entity_id', 'firstname', 'phone_number', 'phone_verified'])
                ->where('phone_number = ?', $phone)
                ->limit(1)
        );

        return $row ?: null;
    }

    public function isTakenByOther(string $phone, int $customerId): bool
    {
        $id = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), ['entity_id'])
                ->where('phone_number = ?', $phone)
                ->where('entity_id <> ?', $customerId)
                ->limit(1)
        );

        return $id !== false && $id !== null;
    }

    /**
     * Sets (or clears, when null) the customer's number and resets the verified flag.
     *
     * @throws LocalizedException when another customer already holds the number
     */
    public function assignPhone(int $customerId, ?string $phone): void
    {
        try {
            $this->connection()->update(
                $this->table(),
                [
                    'phone_number' => $phone,
                    'phone_verified' => 0,
                    'phone_verified_at' => null,
                ],
                ['entity_id = ?' => $customerId]
            );
        } catch (DuplicateException) {
            throw new LocalizedException(
                __('This number cannot be used. Please contact us if you think this is a mistake.')
            );
        }
    }

    public function markVerified(int $customerId): void
    {
        $this->connection()->update(
            $this->table(),
            [
                'phone_verified' => 1,
                'phone_verified_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['entity_id = ?' => $customerId]
        );
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName('customer_entity');
    }
}
