<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Controller\Twilio;

use Local\WhatsAppCheckout\Model\Conversation;
use Local\WhatsAppCheckout\Model\PhoneNumber;
use Local\WhatsAppCheckout\Model\Storage;
use Local\WhatsAppCheckout\Model\TwilioSignatureValidator;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Inbound implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const CONFIG_PREFIX = 'whatsappcheckout/twilio/';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly RawFactory $rawFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly TwilioSignatureValidator $signatureValidator,
        private readonly PhoneNumber $phoneNumber,
        private readonly Storage $storage,
        private readonly Conversation $conversation,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Twilio's signature is validated before any message is processed.
        return true;
    }

    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/xml; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        if (!$this->scopeConfig->isSetFlag(self::CONFIG_PREFIX . 'enabled', ScopeInterface::SCOPE_STORE)) {
            return $result->setHttpResponseCode(503)->setContents('');
        }

        $contentType = (string) $this->request->getHeader('Content-Type');
        $parameters = $this->request->getPostValue();
        if (stripos($contentType, 'application/x-www-form-urlencoded') !== 0 || !is_array($parameters)) {
            return $result->setHttpResponseCode(415)->setContents('');
        }

        $accountSid = $this->config('account_sid');
        $sender = $this->config('sender');
        $webhookUrl = $this->config('webhook_url');
        $authToken = $this->encryptor->decrypt($this->config('auth_token'));
        $signature = (string) $this->request->getHeader('X-Twilio-Signature');
        if (
            $accountSid === '' || $sender === '' || $webhookUrl === ''
            || !$this->signatureValidator->isValid($webhookUrl, $parameters, $signature, $authToken)
            || ($parameters['AccountSid'] ?? null) !== $accountSid
            || ($parameters['To'] ?? null) !== $sender
        ) {
            return $result->setHttpResponseCode(403)->setContents('');
        }

        $messageSid = $parameters['MessageSid'] ?? null;
        $from = $parameters['From'] ?? null;
        $body = $parameters['Body'] ?? null;
        if (
            !is_string($messageSid) || !preg_match('/^[A-Za-z]{2}[A-Za-z0-9]{32}$/D', $messageSid)
            || !is_string($from) || !str_starts_with($from, 'whatsapp:') || !is_string($body)
        ) {
            return $result->setHttpResponseCode(400)->setContents('');
        }
        try {
            $phone = $this->phoneNumber->normalize(substr($from, 9));
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(400)->setContents('');
        }

        $claimed = false;
        try {
            $cached = $this->storage->claimMessage($messageSid);
            if ($cached !== null) {
                return $result->setContents($cached);
            }
            $claimed = true;
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $reply = $this->storage->withConversationLock(
                $websiteId,
                $sender,
                $phone,
                fn (): string => $this->conversation->handle($sender, $phone, $body)
            );
            $xml = $this->twiml($reply);
            $this->storage->finishMessage($messageSid, $xml);
            return $result->setContents($xml);
        } catch (LocalizedException $exception) {
            $xml = $this->twiml($exception->getMessage());
            if ($claimed) {
                $this->storage->finishMessage($messageSid, $xml);
            }
            return $result->setContents($xml);
        } catch (\Throwable $exception) {
            if ($claimed) {
                $this->storage->failMessage($messageSid);
            }
            $this->logger->error(
                'WhatsApp message processing failed.',
                ['exception_type' => get_class($exception)]
            );
            return $result->setHttpResponseCode(503)->setContents('');
        }
    }

    private function twiml(string $reply): string
    {
        return '<Response><Message>'
            . htmlspecialchars($reply, ENT_XML1 | ENT_COMPAT, 'UTF-8')
            . '</Message></Response>';
    }

    private function config(string $field): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PREFIX . $field, ScopeInterface::SCOPE_STORE);
    }
}
