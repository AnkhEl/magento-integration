<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\Model;

use Magento\Customer\Model\Session;

/**
 * Remembers the plain verification code for the logged-in customer, so a page reload can show it again.
 *
 * The code lives only in the customer's server-side session (never in the database, which keeps hashes only).
 * It is shown again only while it still matches the pending code in the database, and it is cleared when the
 * code is verified, expires, is replaced by a new request, or the number is removed.
 */
class PendingCodeStore
{
    private const KEY = 'phone_verification_pending';

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function save(string $code, string $waLink, string $phone, string $expiresAt): void
    {
        $this->session->setData(self::KEY, [
            'code' => $code,
            'wa_link' => $waLink,
            'phone' => $phone,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Returns the remembered code only if it belongs to the code that is currently pending in the database.
     * Any mismatch (expired, replaced by a newer request elsewhere, already used) clears it and returns null.
     *
     * @param string|null $pendingExpiresAt expires_at of the customer's pending DB record, null when none
     * @return array{code: string, wa_link: string, phone: string, expires_at: string}|null
     */
    public function getMatching(?string $pendingExpiresAt): ?array
    {
        $data = $this->session->getData(self::KEY);
        if (!is_array($data)) {
            return null;
        }

        if ($pendingExpiresAt === null || ($data['expires_at'] ?? null) !== $pendingExpiresAt) {
            $this->clear();
            return null;
        }

        return $data;
    }

    public function clear(): void
    {
        $this->session->unsetData(self::KEY);
    }
}
