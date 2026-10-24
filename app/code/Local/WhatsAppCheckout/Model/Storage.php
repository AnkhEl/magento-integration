<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\LocalizedException;

class Storage
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function linkedCustomer(int $websiteId, string $phone): ?int
    {
        $db = $this->resource->getConnection();
        $id = $db->fetchOne($db->select()->from($this->table('local_whatsapp_link'), 'customer_id')
            ->where('website_id = ?', $websiteId)->where('phone = ?', $phone));
        return $id ? (int) $id : null;
    }

    public function linkedPhone(int $customerId): ?string
    {
        $db = $this->resource->getConnection();
        $phone = $db->fetchOne($db->select()->from($this->table('local_whatsapp_link'), 'phone')
            ->where('customer_id = ?', $customerId));
        return $phone === false ? null : (string) $phone;
    }

    public function startChallenge(int $customerId, int $websiteId, string $phone, string $code): void
    {
        $linked = $this->linkedCustomer($websiteId, $phone);
        if ($linked !== null && $linked !== $customerId) {
            throw new LocalizedException(__('This number is already linked to another account.'));
        }
        $db = $this->resource->getConnection();
        $db->insertOnDuplicate($this->table('local_whatsapp_challenge'), [
            'customer_id' => $customerId,
            'website_id' => $websiteId,
            'phone' => $phone,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
            'attempts' => 0,
        ], ['website_id', 'phone', 'code_hash', 'expires_at', 'attempts']);
    }

    public function completeChallenge(int $websiteId, string $phone, string $code): ?int
    {
        $db = $this->resource->getConnection();
        $db->beginTransaction();
        try {
            $rows = $db->fetchAll($db->select()->from($this->table('local_whatsapp_challenge'))
                ->where('website_id = ?', $websiteId)->where('phone = ?', $phone)->forUpdate(true));
            foreach ($rows as $row) {
                if (strtotime($row['expires_at'] . ' UTC') < time() || (int) $row['attempts'] >= 5) {
                    continue;
                }
                if (password_verify($code, $row['code_hash'])) {
                    $customerId = (int) $row['customer_id'];
                    $link = [
                        'customer_id' => $customerId,
                        'website_id' => $websiteId,
                        'phone' => $phone,
                        'verified_at' => gmdate('Y-m-d H:i:s'),
                    ];
                    if ($this->linkedPhone($customerId) !== null) {
                        $db->update(
                            $this->table('local_whatsapp_link'),
                            $link,
                            ['customer_id = ?' => $customerId]
                        );
                    } else {
                        $db->insert($this->table('local_whatsapp_link'), $link);
                    }
                    $db->delete($this->table('local_whatsapp_conversation'), [
                        'website_id = ?' => $websiteId,
                        'phone = ?' => $phone,
                    ]);
                    $db->delete($this->table('local_whatsapp_challenge'), ['customer_id = ?' => $customerId]);
                    $db->commit();
                    return $customerId;
                }
                $db->update(
                    $this->table('local_whatsapp_challenge'),
                    ['attempts' => (int) $row['attempts'] + 1],
                    ['customer_id = ?' => $row['customer_id']]
                );
            }
            $db->commit();
            return null;
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function conversation(int $websiteId, string $sender, string $phone): array
    {
        $db = $this->resource->getConnection();
        $row = $db->fetchRow($db->select()->from($this->table('local_whatsapp_conversation'))
            ->where('website_id = ?', $websiteId)->where('sender = ?', $sender)->where('phone = ?', $phone));
        if (!$row) {
            return [];
        }
        $state = json_decode($row['state_json'], true);
        if (!is_array($state)) {
            return [];
        }
        $retention = in_array($state['step'] ?? '', ['placing', 'complete'], true) ? 604800 : 1800;
        return strtotime($row['updated_at'] . ' UTC') < time() - $retention ? [] : $state;
    }

    public function saveConversation(int $websiteId, string $sender, string $phone, array $state): void
    {
        $this->resource->getConnection()->insertOnDuplicate($this->table('local_whatsapp_conversation'), [
            'website_id' => $websiteId,
            'sender' => $sender,
            'phone' => $phone,
            'state_json' => json_encode($state, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['state_json', 'updated_at']);
    }

    public function withConversationLock(int $websiteId, string $sender, string $phone, callable $work): string
    {
        $db = $this->resource->getConnection();
        $name = 'whatsapp_' . substr(hash('sha256', $websiteId . ':' . $sender . ':' . $phone), 0, 48);
        if ((int) $db->fetchOne('SELECT GET_LOCK(?, 5)', [$name]) !== 1) {
            throw new \RuntimeException('WhatsApp conversation is busy.');
        }
        try {
            return $work();
        } finally {
            $db->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    public function claimMessage(string $messageSid): ?string
    {
        $db = $this->resource->getConnection();
        $table = $this->table('local_whatsapp_message');
        try {
            $db->insert($table, [
                'message_sid' => $messageSid,
                'status' => 'processing',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            return null;
        } catch (DuplicateException $exception) {
            // A retry can reuse the finished TwiML without repeating the order action.
        }
        $row = $db->fetchRow($db->select()->from($table)->where('message_sid = ?', $messageSid));
        if ($row && $row['status'] === 'done') {
            return (string) $row['response_xml'];
        }
        if ($row && strtotime($row['created_at'] . ' UTC') < time() - 60) {
            $updated = $db->update($table, ['created_at' => gmdate('Y-m-d H:i:s')], [
                'message_sid = ?' => $messageSid,
                'status = ?' => 'processing',
                'created_at < ?' => gmdate('Y-m-d H:i:s', time() - 60),
            ]);
            if ($updated) {
                return null;
            }
        }
        throw new \RuntimeException('Message is already being processed.');
    }

    public function finishMessage(string $messageSid, string $xml): void
    {
        $this->resource->getConnection()->update(
            $this->table('local_whatsapp_message'),
            ['status' => 'done', 'response_xml' => $xml],
            ['message_sid = ?' => $messageSid]
        );
    }

    public function failMessage(string $messageSid): void
    {
        $this->resource->getConnection()->delete(
            $this->table('local_whatsapp_message'),
            ['message_sid = ?' => $messageSid, 'status = ?' => 'processing']
        );
    }

    public function cleanup(): void
    {
        $db = $this->resource->getConnection();
        $db->delete($this->table('local_whatsapp_challenge'), [
            'expires_at < ?' => gmdate('Y-m-d H:i:s', time() - 86400),
        ]);
        $db->delete($this->table('local_whatsapp_conversation'), [
            'updated_at < ?' => gmdate('Y-m-d H:i:s', time() - 604800),
        ]);
        $db->delete($this->table('local_whatsapp_message'), [
            'created_at < ?' => gmdate('Y-m-d H:i:s', time() - 604800),
        ]);
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
