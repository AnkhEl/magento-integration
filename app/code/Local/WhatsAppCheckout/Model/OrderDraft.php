<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class OrderDraft
{
    public function __construct(
        private readonly QuoteFactory $quoteFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly MethodList $methodList,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array{summary: string, fingerprint: string} */
    public function preview(int $customerId, string $phone, array $items): array
    {
        return $this->describe($this->buildQuote($customerId, $phone, $items));
    }

    /** @return array{changed: bool, summary?: string, fingerprint?: string, quote_id?: int} */
    public function preparePlacement(int $customerId, string $phone, array $items, string $expectedFingerprint): array
    {
        $quote = $this->buildQuote($customerId, $phone, $items);
        $current = $this->describe($quote);
        if (!hash_equals($expectedFingerprint, $current['fingerprint'])) {
            return ['changed' => true] + $current;
        }
        $this->cartRepository->save($quote);
        return ['changed' => false, 'quote_id' => (int) $quote->getId()];
    }

    public function placeSaved(int $quoteId): string
    {
        $existing = $this->findOrderByQuote($quoteId);
        if ($existing !== null) {
            return $existing;
        }
        $orderId = $this->cartManagement->placeOrder($quoteId);
        $order = $this->orderRepository->get($orderId);
        return (string) $order->getIncrementId();
    }

    public function findOrderByQuote(int $quoteId): ?string
    {
        $order = $this->orderCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quoteId)->setPageSize(1)->getFirstItem();
        return $order->getId() ? (string) $order->getIncrementId() : null;
    }

    private function buildQuote(int $customerId, string $phone, array $items): Quote
    {
        $store = $this->storeManager->getStore();
        $customer = $this->customerRepository->getById($customerId);
        if ((int) $customer->getWebsiteId() !== (int) $store->getWebsiteId()) {
            throw new LocalizedException(__('This account is not available in this store.'));
        }
        if (!$customer->getDefaultShipping()) {
            throw new LocalizedException(__('Add a default shipping address in My Account before ordering.'));
        }
        $shipping = $this->addressRepository->getById((int) $customer->getDefaultShipping());
        $billing = $customer->getDefaultBilling()
            ? $this->addressRepository->getById((int) $customer->getDefaultBilling()) : $shipping;

        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $baseCurrency = $store->getBaseCurrency();
        $quoteCurrency = $store->getCurrentCurrency();
        $quote->setBaseCurrencyCode($baseCurrency->getCode());
        $quote->setStoreCurrencyCode($baseCurrency->getCode());
        $quote->setQuoteCurrencyCode($quoteCurrency->getCode());
        $quote->setBaseToQuoteRate($baseCurrency->getRate($quoteCurrency));
        $quote->setCustomer($customer);
        $quote->setCustomerIsGuest(false);
        $quote->setIsActive(true);
        foreach ($items as $sku => $qty) {
            $product = $this->productRepository->get($sku, false, (int) $store->getId(), true);
            if ($product->getTypeId() !== ProductType::TYPE_SIMPLE || $product->getRequiredOptions()) {
                throw new LocalizedException(__('One of the products needs options or is unavailable.'));
            }
            $added = $quote->addProduct($product, (int) $qty);
            if (is_string($added)) {
                throw new LocalizedException(__($added));
            }
        }
        $quote->getShippingAddress()->importCustomerAddressData($shipping);
        $quote->getShippingAddress()->setCustomerAddressId((int) $shipping->getId());
        $quote->getShippingAddress()->setTelephone($phone);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->getShippingAddress()->collectShippingRates();
        if (!$quote->getShippingAddress()->getShippingRateByCode('flatrate_flatrate')) {
            throw new LocalizedException(__('Flat-rate shipping is unavailable for the saved address.'));
        }
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
        $quote->getBillingAddress()->importCustomerAddressData($billing);
        $quote->getBillingAddress()->setCustomerAddressId((int) $billing->getId());
        $quote->getBillingAddress()->setTelephone($phone);
        $quote->getPayment()->setMethod('cashondelivery');
        $quote->collectTotals();
        $methods = $this->methodList->getAvailableMethods($quote);
        $codAvailable = false;
        foreach ($methods as $method) {
            if ($method->getCode() === 'cashondelivery') {
                $codAvailable = true;
                break;
            }
        }
        if (!$codAvailable) {
            throw new LocalizedException(__('Cash on delivery is unavailable for this order.'));
        }
        return $quote;
    }

    /** @return array{summary: string, fingerprint: string} */
    private function describe(Quote $quote): array
    {
        $lines = [];
        $fingerprintLines = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $lines[] = sprintf('%s x%d', $item->getName(), (int) $item->getQty());
            $fingerprintLines[] = [$item->getSku(), (int) $item->getQty(), (string) $item->getPrice()];
        }
        $address = $quote->getShippingAddress();
        $formattedAddress = implode(', ', array_filter([
            trim((string) $address->getFirstname() . ' ' . (string) $address->getLastname()),
            implode(' ', (array) $address->getStreet()),
            $address->getCity(),
            $address->getRegion(),
            $address->getPostcode(),
            $address->getCountryId(),
        ]));
        $currency = (string) $quote->getQuoteCurrencyCode();
        $storeId = (int) $quote->getStoreId();
        $money = fn ($amount) => $this->priceCurrency->format((float) $amount, false, 2, $storeId, $currency);
        $summary = implode("\n", $lines)
            . "\nShip to: " . $formattedAddress
            . "\nShipping: " . $money($address->getShippingAmount())
            . "\nTax: " . $money($address->getTaxAmount())
            . "\nTotal: " . $money($quote->getGrandTotal())
            . "\nPayment: cash on delivery";
        $fingerprint = hash('sha256', json_encode([
            $fingerprintLines,
            $address->getCustomerAddressId(),
            $formattedAddress,
            $address->getShippingMethod(),
            $address->getShippingAmount(),
            $address->getTaxAmount(),
            $quote->getGrandTotal(),
            $currency,
        ], JSON_THROW_ON_ERROR));
        return ['summary' => $summary, 'fingerprint' => $fingerprint];
    }
}
