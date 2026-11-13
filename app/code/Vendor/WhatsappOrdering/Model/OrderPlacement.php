<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Vendor\WhatsappOrdering\Api\Data\PlacedOrderInterface;
use Vendor\WhatsappOrdering\Api\Data\PlacedOrderInterfaceFactory;
use Vendor\WhatsappOrdering\Api\OrderPlacementInterface;

/**
 * Places the confirmed order. The idempotency key is claimed first (unique database key), so two requests with the
 * same key can never both reach placeOrder. Failures before an order exists release the key; once the order exists
 * the key is marked placed and later calls just report that order.
 */
class OrderPlacement implements OrderPlacementInterface
{
    private const KEY_PATTERN = '/^[A-Za-z0-9._:-]{8,64}$/';
    private const FAILED = 'We could not place your order right now. Nothing was ordered. Please try again.';

    public function __construct(
        private readonly OrderDraftBuilder $builder,
        private readonly IdempotencyStore $store,
        private readonly Config $config,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PlacedOrderInterfaceFactory $resultFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function place($phone, $items, $idempotencyKey): PlacedOrderInterface
    {
        $key = (string) $idempotencyKey;
        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new InputException(__('The idempotency key must be 8 to 64 letters, digits or . _ : -'));
        }

        // Same checks as the preview. Malformed input is thrown here, before anything is claimed.
        $draft = $this->builder->build($phone, $items);
        if ($draft->customerId === 0) {
            return $this->failure($draft->preview->getProblems());
        }

        // A key that was already used: report what happened, never place again.
        $existing = $this->store->find($key);
        if ($existing !== null) {
            return $this->replay($existing, $draft->customerId);
        }
        if (!$draft->preview->getOk() || $draft->quote === null) {
            return $this->failure($draft->preview->getProblems());
        }

        if (!$this->store->claim($key, $draft->customerId)) {
            // Lost a race with another request holding the same key.
            return $this->replay($this->store->find($key), $draft->customerId);
        }

        $quote = $draft->quote;
        try {
            // On a quote that is not saved yet the payment does not know its quote, so link them first.
            $payment = $quote->getPayment();
            $payment->setQuote($quote);
            $payment->importData(['method' => $this->config->getPaymentMethod()]);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $orderId = (int) $this->cartManagement->placeOrder((int) $quote->getId());
        } catch (\Throwable $e) {
            $this->logger->error('WhatsApp order was not placed: ' . $e->getMessage(), ['exception' => $e]);
            $this->store->release($key);
            $this->discard($quote);
            return $this->failure([self::FAILED]);
        }

        // The order exists from here on: nothing below may undo or hide it.
        $number = '';
        $total = null;
        $currency = null;
        try {
            $order = $this->orderRepository->get($orderId);
            $number = (string) $order->getIncrementId();
            $total = (float) $order->getGrandTotal();
            $currency = (string) $order->getOrderCurrencyCode();
            $order->addCommentToStatusHistory('Placed from WhatsApp.');
            $this->orderRepository->save($order);
        } catch (\Throwable $e) {
            $this->logger->error('WhatsApp order ' . $orderId . ' placed, follow-up failed: ' . $e->getMessage());
        }
        $this->store->markPlaced($key, $orderId, $number);

        $result = $this->resultFactory->create();
        $result->setOk(true);
        $result->setProblems([]);
        $result->setOrderNumber($number);
        $result->setGrandTotal($total);
        $result->setCurrency($currency);
        $result->setAlreadyPlaced(false);
        return $result;
    }

    /** @param array{customer_id:int,status:string,order_id:?int,increment_id:?string}|null $existing */
    private function replay(?array $existing, int $customerId): PlacedOrderInterface
    {
        if ($existing === null || $existing['customer_id'] !== $customerId) {
            return $this->failure(['This confirmation cannot be used.']);
        }
        if ($existing['status'] !== IdempotencyStore::PLACED || $existing['order_id'] === null) {
            return $this->failure(['This order is still being processed. Please wait a moment.']);
        }
        $result = $this->resultFactory->create();
        $result->setOk(true);
        $result->setProblems([]);
        $result->setOrderNumber((string) $existing['increment_id']);
        $result->setAlreadyPlaced(true);
        try {
            $order = $this->orderRepository->get($existing['order_id']);
            $result->setGrandTotal((float) $order->getGrandTotal());
            $result->setCurrency((string) $order->getOrderCurrencyCode());
        } catch (\Throwable) {
            // The order number is enough.
        }
        return $result;
    }

    private function discard(\Magento\Quote\Model\Quote $quote): void
    {
        try {
            if ($quote->getId()) {
                $this->cartRepository->delete($quote);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not remove a quote left by a failed WhatsApp order: ' . $e->getMessage());
        }
    }

    /** @param string[] $problems */
    private function failure(array $problems): PlacedOrderInterface
    {
        $result = $this->resultFactory->create();
        $result->setOk(false);
        $result->setProblems($problems);
        $result->setAlreadyPlaced(false);
        return $result;
    }
}
