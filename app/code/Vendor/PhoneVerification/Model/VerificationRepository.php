<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Persistence for the phone_verification table. All timestamps are UTC strings (Y-m-d H:i:s).
 */
class VerificationRepository
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function create(int $customerId, string $phone, string $codeHash, int $ttlSeconds): string
    {
        $now = time();
        $expiresAt = gmdate('Y-m-d H:i:s', $now + $ttlSeconds);

        $this->connection()->insert($this->table(), [
            'customer_id' => $customerId,
            'phone' => $phone,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'created_at' => gmdate('Y-m-d H:i:s', $now),
            'expires_at' => $expiresAt,
            'consumed_at' => null,
        ]);

        return $expiresAt;
    }

    /** Latest code for this number that is neither consumed nor expired. */
    public function findPendingByPhone(string $phone): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table())
                ->where('phone = ?', $phone)
                ->where('consumed_at IS NULL')
                ->where('expires_at > ?', gmdate('Y-m-d H:i:s'))
                ->order('verification_id DESC')
                ->limit(1)
        );

        return $row ?: null;
    }

    public function findPendingByCustomer(int $customerId): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table())
                ->where('customer_id = ?', $customerId)
                ->where('consumed_at IS NULL')
                ->where('expires_at > ?', gmdate('Y-m-d H:i:s'))
                ->order('verification_id DESC')
                ->limit(1)
        );

        return $row ?: null;
    }

    /** Seconds since the customer last requested a code, or null when they never did. */
    public function secondsSinceLastRequest(int $customerId): ?int
    {
        $createdAt = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(), ['created_at'])
                ->where('customer_id = ?', $customerId)
                ->order('verification_id DESC')
                ->limit(1)
        );

        if ($createdAt === false || $createdAt === null) {
            return null;
        }

        return max(0, time() - (int) strtotime($createdAt . ' UTC'));
    }

    public function incrementAttempts(int $verificationId): void
    {
        $this->connection()->update(
            $this->table(),
            ['attempts' => new \Zend_Db_Expr('attempts + 1')],
            ['verification_id = ?' => $verificationId]
        );
    }

    public function markConsumed(int $verificationId): void
    {
        $this->connection()->update(
            $this->table(),
            ['consumed_at' => gmdate('Y-m-d H:i:s')],
            ['verification_id = ?' => $verificationId]
        );
    }

    /** Invalidates every pending code of a customer (new request, number change or removal). */
    public function invalidateForCustomer(int $customerId): void
    {
        $this->connection()->update(
            $this->table(),
            ['consumed_at' => gmdate('Y-m-d H:i:s')],
            ['customer_id = ?' => $customerId, 'consumed_at IS NULL']
        );
    }

    public function beginTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection()->commit();
    }

    public function rollBack(): void
    {
        $this->connection()->rollBack();
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(): string
    {
        return $this->resource->getTableName('phone_verification');
    }
}
