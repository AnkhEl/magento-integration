<?php
declare(strict_types=1);

namespace Vendor\PhoneVerification\ViewModel;

use Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Vendor\PhoneVerification\Model\Config;
use Vendor\PhoneVerification\Model\VerificationService;

class PhoneAccount implements ArgumentInterface
{
    public function __construct(
        private readonly Session $customerSession,
        private readonly VerificationService $service,
        private readonly Config $config,
        private readonly FormKey $formKey,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->config->isEnabled() && $this->config->getWhatsappSender() !== '';
    }

    /** @return array{phone: string|null, verified: bool, pending: bool, expires_in: int} */
    public function getStatus(): array
    {
        return $this->service->getStatus((int) $this->customerSession->getCustomerId());
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

    /** JSON for the x-magento-init configuration of the page widget. */
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
