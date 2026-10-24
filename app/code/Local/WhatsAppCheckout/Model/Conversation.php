<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class Conversation
{
    public function __construct(
        private readonly Storage $storage,
        private readonly ProductSearch $productSearch,
        private readonly OrderDraft $orderDraft,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function handle(string $sender, string $phone, string $message): string
    {
        $websiteId = (int) $this->storeManager->getWebsite()->getId();
        $message = trim($message);
        if (preg_match('/^LINK\s+([0-9]{6})$/iD', $message, $matches)) {
            $customerId = $this->storage->completeChallenge($websiteId, $phone, $matches[1]);
            if ($customerId === null) {
                return 'The link code is invalid or expired. Sign in to My Account for a new code.';
            }
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('phone_number', $phone);
            $this->customerRepository->save($customer);
            return 'Your WhatsApp number is linked. Send SHOP to start an order.';
        }

        $customerId = $this->storage->linkedCustomer($websiteId, $phone);
        if ($customerId === null) {
            return 'Create or sign in to your account, then link this WhatsApp number: '
                . $this->urlBuilder->getUrl('whatsappcheckout/account/link', ['_secure' => true]);
        }
        $state = $this->storage->conversation($websiteId, $sender, $phone);
        $upper = strtoupper($message);
        if (($state['step'] ?? '') === 'placing' && $upper !== 'CONFIRM') {
            return 'The previous order is being finalized. Send CONFIRM to get its order number.';
        }
        if ($upper === 'SHOP') {
            $state = ['step' => 'search', 'items' => []];
            $this->save($websiteId, $sender, $phone, $state);
            return 'What product are you looking for? Reply with a product name.';
        }
        if (($state['step'] ?? '') === 'complete') {
            return 'Order ' . $state['order'] . ' was already placed. Send SHOP to start another order.';
        }
        if ($upper === 'CANCEL') {
            $this->save($websiteId, $sender, $phone, []);
            return 'Your WhatsApp order draft was cancelled. Send SHOP to begin again.';
        }
        if ($state === []) {
            return 'Send SHOP to browse products and create an order.';
        }
        if ($upper === 'CART') {
            return $this->cartText($state) . "\nSend CHECKOUT to review, or search for another product.";
        }
        if (preg_match('/^REMOVE\s+([1-9]|10)$/iD', $message, $matches)) {
            $skus = array_keys($state['items'] ?? []);
            $sku = $skus[(int) $matches[1] - 1] ?? null;
            if ($sku === null) {
                return 'That cart line does not exist. Send CART to see the lines.';
            }
            unset($state['items'][$sku]);
            $state['step'] = 'search';
            unset($state['fingerprint'], $state['quote_id']);
            $this->save($websiteId, $sender, $phone, $state);
            return 'Removed ' . $sku . ".\n" . $this->cartText($state);
        }
        if ($upper === 'CHECKOUT') {
            if (empty($state['items'])) {
                return 'Your cart is empty. Search for a product first.';
            }
            $preview = $this->orderDraft->preview($customerId, $phone, $state['items']);
            $state['step'] = 'confirm';
            $state['fingerprint'] = $preview['fingerprint'];
            unset($state['quote_id']);
            $this->save($websiteId, $sender, $phone, $state);
            return $preview['summary'] . "\nReply CONFIRM to place this order, or CANCEL.";
        }
        if ($upper === 'CONFIRM') {
            return $this->confirm($websiteId, $sender, $phone, $customerId, $state);
        }

        if (($state['step'] ?? '') === 'quantity') {
            if (!preg_match('/^(10|[1-9])$/D', $message)) {
                return 'Reply with a whole-number quantity from 1 to 10.';
            }
            $sku = $state['selected_sku'];
            $existing = (int) ($state['items'][$sku] ?? 0);
            if ($existing + (int) $message > 10) {
                return 'A cart line can contain at most 10 items. Choose a smaller quantity.';
            }
            if ($existing === 0 && count($state['items']) >= 10) {
                return 'This cart already has 10 product lines. Send CHECKOUT or REMOVE a line.';
            }
            $state['items'][$sku] = $existing + (int) $message;
            $state['step'] = 'search';
            unset($state['selected_sku'], $state['results'], $state['fingerprint']);
            $this->save($websiteId, $sender, $phone, $state);
            return "Added $sku x$message. Search for another product, send CART, or CHECKOUT.";
        }
        if (($state['step'] ?? '') === 'choose') {
            if (preg_match('/^[1-5]$/D', $message)) {
                $selection = $state['results'][(int) $message - 1] ?? null;
                if (!$selection) {
                    return 'That result does not exist. Search for another product.';
                }
                $state['selected_sku'] = $selection['sku'];
                $state['step'] = 'quantity';
                $this->save($websiteId, $sender, $phone, $state);
                return 'How many ' . $selection['name'] . '? Reply with a quantity from 1 to 10.';
            }
        }
        if (strlen($message) < 2 || strlen($message) > 60) {
            return 'Reply with a product name of 2 to 60 characters, or send CHECKOUT.';
        }
        $results = $this->productSearch->find($message);
        if ($results === []) {
            return 'No matching saleable simple products. Try another product name.';
        }
        $state['step'] = 'choose';
        $state['results'] = $results;
        $this->save($websiteId, $sender, $phone, $state);
        $lines = [];
        foreach ($results as $index => $product) {
            $lines[] = ($index + 1) . '. ' . $product['name'] . ' (' . $product['sku'] . ')';
        }
        return implode("\n", $lines) . "\nReply with a result number.";
    }

    private function confirm(int $websiteId, string $sender, string $phone, int $customerId, array $state): string
    {
        if (($state['step'] ?? '') === 'complete') {
            return 'Order ' . $state['order'] . ' was already placed.';
        }
        if (($state['step'] ?? '') === 'placing' && !empty($state['quote_id'])) {
            $order = $this->orderDraft->placeSaved((int) $state['quote_id']);
        } elseif (($state['step'] ?? '') === 'confirm' && !empty($state['fingerprint'])) {
            $prepared = $this->orderDraft->preparePlacement(
                $customerId,
                $phone,
                $state['items'],
                $state['fingerprint']
            );
            if ($prepared['changed']) {
                $state['fingerprint'] = $prepared['fingerprint'];
                $this->save($websiteId, $sender, $phone, $state);
                return 'The order changed. Review the current details:' . "\n"
                    . $prepared['summary'] . "\nReply CONFIRM again to place it.";
            }
            $state['step'] = 'placing';
            $state['quote_id'] = $prepared['quote_id'];
            $this->save($websiteId, $sender, $phone, $state);
            $order = $this->orderDraft->placeSaved((int) $state['quote_id']);
        } else {
            return 'Send CHECKOUT to review the address and total before confirming.';
        }
        $state['step'] = 'complete';
        $state['order'] = $order;
        $this->save($websiteId, $sender, $phone, $state);
        return 'Order ' . $order . ' has been placed. Pay cash on delivery. Send SHOP to start another order.';
    }

    private function cartText(array $state): string
    {
        if (empty($state['items'])) {
            return 'Your cart is empty.';
        }
        $lines = [];
        $index = 1;
        foreach ($state['items'] as $sku => $quantity) {
            $lines[] = $index++ . '. ' . $sku . ' x' . $quantity;
        }
        return "Your cart:\n" . implode("\n", $lines);
    }

    private function save(int $websiteId, string $sender, string $phone, array $state): void
    {
        $this->storage->saveConversation($websiteId, $sender, $phone, $state);
    }
}
