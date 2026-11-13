<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Framework\App\ResourceConnection;

/** Remembers which order attempts have been made, so a retried confirmation cannot create a second order. */
class IdempotencyStore
{
    public const PENDING = 'pending';
    public const PLACED = 'placed';
    private const TABLE = 'vendor_whatsapp_order';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /** True if this call created the row (so this call owns the attempt); false if the key was already used. */
    public function claim(string $key, int $customerId): bool
    {
        $db = $this->resource->getConnection();
        try {
            $db->insert(
                $this->resource->getTableName(self::TABLE),
                ['idempotency_key' => $key, 'customer_id' => $customerId, 'status' => self::PENDING]
            );
            return true;
        } catch (\Throwable $e) {
            // The unique key refuses a second row with the same key. Any other failure is a real error.
            if ($this->find($key) !== null) {
                return false;
            }
            throw $e;
        }
    }

    /** @return array{customer_id:int,status:string,order_id:?int,increment_id:?string}|null */
    public function find(string $key): ?array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow(
            $db->select()->from($this->resource->getTableName(self::TABLE))->where('idempotency_key = ?', $key)
        );
        if (!$row) {
            return null;
        }
        return [
            'customer_id' => (int) $row['customer_id'],
            'status' => (string) $row['status'],
            'order_id' => $row['order_id'] === null ? null : (int) $row['order_id'],
            'increment_id' => $row['increment_id'] === null ? null : (string) $row['increment_id'],
        ];
    }

    public function markPlaced(string $key, int $orderId, string $incrementId): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName(self::TABLE),
            ['status' => self::PLACED, 'order_id' => $orderId, 'increment_id' => $incrementId],
            ['idempotency_key = ?' => $key]
        );
    }

    /** Frees the key after an attempt that created no order, so the customer can try again. */
    public function release(string $key): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['idempotency_key = ?' => $key, 'status = ?' => self::PENDING]
        );
    }
}
