<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\ViewModel;

use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Vendor\PhoneVerification\Model\Config;
use Vendor\PhoneVerification\Model\PendingCodeStore;
use Vendor\PhoneVerification\Model\VerificationService;

class PhoneAccount implements ArgumentInterface
{
    /** @var array{phone: string|null, verified: bool, pending: bool, expires_in: int, expires_at: string|null}|null */
    private ?array $status = null;

    public function __construct(
        private readonly Session $customerSession,
        private readonly VerificationService $service,
        private readonly PendingCodeStore $pendingCodeStore,
        private readonly Config $config,
        private readonly FormKey $formKey,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->config->isEnabled() && $this->config->getWhatsappSender() !== '';
    }

    /** @return array{phone: string|null, verified: bool, pending: bool, expires_in: int, expires_at: string|null} */
    public function getStatus(): array
    {
        // Read once per page so the template, the pending code and the widget config agree with each other.
        return $this->status ??= $this->service->getStatus((int) $this->customerSession->getCustomerId());
    }

    /**
     * The code to show again after a reload, or null when there is none to show
     * (nothing pending, or the remembered code is no longer the current one).
     *
     * @return array{code: string, waLink: string, phone: string, expiresIn: int}|null
     */
    public function getPendingCode(): ?array
    {
        $status = $this->getStatus();

        $stored = $this->pendingCodeStore->getMatching($status['pending'] ? $status['expires_at'] : null);
        if ($stored === null) {
            return null;
        }

        return [
            'code' => $stored['code'],
            'waLink' => $stored['wa_link'],
            'phone' => $stored['phone'],
            'expiresIn' => $status['expires_in'],
        ];
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getRequestUrl(): string
    {
        return $this->urlBuilder->getUrl('phone/account/requestcode');
    }

    public function getStatusUrl(): string
    {
        return $this->urlBuilder->getUrl('phone/account/status');
    }

    public function getRemoveUrl(): string
    {
        return $this->urlBuilder->getUrl('phone/account/remove');
    }

    /** Configuration for the page widget (rendered as JSON in the x-magento-init block). */
    public function getWidgetConfig(): array
    {
        return [
            'requestUrl' => $this->getRequestUrl(),
            'statusUrl' => $this->getStatusUrl(),
            'removeUrl' => $this->getRemoveUrl(),
            'formKey' => $this->getFormKey(),
            'pollIntervalMs' => 3000,
        ];
    }
}
