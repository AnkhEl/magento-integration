<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\PhoneVerification\Model\VerificationService;
use Vendor\WhatsappOrdering\Api\Data\OrderItemInterface;
use Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterface;
use Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterfaceFactory;
use Vendor\WhatsappOrdering\Api\Data\PreviewLineInterfaceFactory;
use Vendor\WhatsappOrdering\Api\Data\ShippingOptionInterfaceFactory;
use Vendor\WhatsappOrdering\Api\OrderPreviewManagementInterface;

/**
 * Builds an order preview from an unsaved quote: nothing is written to the database and no stock is reserved.
 *
 * Judged for the default store view (what a REST call without a store code uses), for the verified customer's default
 * shipping address, with the one shipping and payment method chosen in the admin. Problems are returned in the answer
 * (ok = false) so the middleware can show them to the customer; only malformed input is thrown.
 */
class OrderPreviewManagement implements OrderPreviewManagementInterface
{
    private const MAX_LINES = 20;

    public function __construct(
        private readonly VerificationService $verification,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly QuoteFactory $quoteFactory,
        private readonly PaymentHelper $paymentHelper,
        private readonly Config $config,
        private readonly OrderPreviewInterfaceFactory $previewFactory,
        private readonly PreviewLineInterfaceFactory $lineFactory,
        private readonly ShippingOptionInterfaceFactory $shippingOptionFactory
    ) {
    }

    /**
     * @param string $phone
     * @param OrderItemInterface[] $items
     * @throws InputException
     */
    public function preview($phone, $items): OrderPreviewInterface
    {
        $items = is_array($items) ? $items : [];
        if ($items === [] || count($items) > self::MAX_LINES) {
            throw new InputException(__('Send between 1 and %1 items.', self::MAX_LINES));
        }
        foreach ($items as $item) {
            if (trim((string) $item->getSku()) === '' || (int) $item->getQty() < 1) {
                throw new InputException(__('Every item needs a SKU and a quantity of at least 1.'));
            }
        }

        $preview = $this->previewFactory->create();
        $preview->setOk(false);
        $preview->setProblems([]);
        $preview->setLines([]);
        $preview->setAvailableShipping([]);
        $problems = [];

        // 1. Who is asking: only a verified customer may order.
        $found = $this->verification->lookup((string) $phone);
        if (empty($found['found']) || empty($found['phone_verified'])) {
            return $this->finish($preview, ['This number is not verified yet.']);
        }

        // 2. The default shipping address.
        try {
            $customer = $this->customerRepository->getById((int) $found['customer_id']);
            $shippingId = $customer->getDefaultShipping();
            if (!$shippingId) {
                return $this->finish($preview, [
                    'There is no default delivery address on your account. Please add one on the website.',
                ]);
            }
            $shippingAddress = $this->addressRepository->getById((int) $shippingId);
            $billingId = $customer->getDefaultBilling();
            $billingAddress = $billingId ? $this->addressRepository->getById((int) $billingId) : $shippingAddress;
        } catch (NoSuchEntityException) {
            return $this->finish($preview, ['Your account details could not be loaded.']);
        }
        $preview->setDeliveryName(trim($shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname()));
        $preview->setDeliveryAddress($this->oneLine($shippingAddress));

        // 3. An unsaved quote for the default store view.
        $store = $this->storeManager->getStore();
        $quote = $this->quoteFactory->create();
        $quote->setStore($store);
        $quote->setStoreId((int) $store->getId());
        $quote->setBaseCurrencyCode($store->getBaseCurrencyCode());
        $quote->setQuoteCurrencyCode($store->getBaseCurrencyCode());
        $quote->setCustomer($customer);
        $quote->setCustomerIsGuest(false);

        // 4. Items. Magento itself refuses what cannot be sold (stock, minimum or maximum quantity, disabled).
        $totalQty = 0;
        foreach ($items as $item) {
            $sku = trim((string) $item->getSku());
            $qty = (int) $item->getQty();
            $totalQty += $qty;
            try {
                $product = $this->productRepository->get($sku, false, (int) $store->getId());
                $result = $quote->addProduct($product, new DataObject(['qty' => $qty]));
                if (is_string($result)) {
                    $problems[] = $sku . ': ' . $result;
                }
            } catch (NoSuchEntityException) {
                $problems[] = $sku . ': this SKU was not found.';
            } catch (LocalizedException $e) {
                $problems[] = $sku . ': ' . $e->getMessage();
            }
        }
        if ($problems !== []) {
            return $this->finish($preview, $problems);
        }

        // 5. Addresses and shipping rates.
        $quote->getBillingAddress()->importCustomerAddressData($billingAddress);
        $shipping = $quote->getShippingAddress();
        $shipping->importCustomerAddressData($shippingAddress);
        $shipping->setCollectShippingRates(true);
        $shipping->collectShippingRates();

        $options = [];
        $chosen = null;
        $wanted = $this->config->getShippingMethod();
        foreach ($shipping->getAllShippingRates() as $rate) {
            if ($rate->getErrorMessage()) {
                continue;
            }
            $option = $this->shippingOptionFactory->create();
            $option->setCode((string) $rate->getCode());
            $option->setLabel(trim($rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(), ' -'));
            $option->setAmount($rate->getPrice() === null ? null : (float) $rate->getPrice());
            $options[] = $option;
            if ($rate->getCode() === $wanted) {
                $chosen = $rate;
            }
        }
        $preview->setAvailableShipping($options);

        if ($chosen === null) {
            $problems[] = $wanted === ''
                ? 'No shipping method is configured for WhatsApp orders.'
                : 'Delivery to your address is not available right now.';
            return $this->finish($preview, $problems, $quote);
        }
        $shipping->setShippingMethod($wanted);
        $preview->setShippingCode($wanted);
        $preview->setShippingLabel(trim($chosen->getCarrierTitle() . ' - ' . $chosen->getMethodTitle(), ' -'));

        // 6. Payment method must be usable for this quote.
        $paymentCode = $this->config->getPaymentMethod();
        try {
            $method = $this->paymentHelper->getMethodInstance($paymentCode);
            $method->setInfoInstance($quote->getPayment());
            if (!$method->isAvailable($quote)) {
                $problems[] = 'Cash on delivery is not available for this order.';
            } else {
                $preview->setPaymentLabel((string) $method->getTitle());
            }
        } catch (LocalizedException) {
            $problems[] = 'The payment method is not configured correctly.';
        }

        // 7. Totals, as Magento calculates them.
        $quote->collectTotals();
        $this->fillTotals($preview, $quote);

        // 8. Limits chosen in the admin, and anything Magento flagged on the quote.
        $maxItems = $this->config->getMaxItems();
        if ($maxItems > 0 && $totalQty > $maxItems) {
            $problems[] = sprintf('An order can have at most %d items.', $maxItems);
        }
        $maxValue = $this->config->getMaxOrderValue();
        if ($maxValue > 0 && (float) $quote->getGrandTotal() > $maxValue) {
            $problems[] = sprintf(
                'An order can be at most %s %s.',
                number_format($maxValue, 2, '.', ''),
                $quote->getQuoteCurrencyCode()
            );
        }
        if ($quote->getHasError()) {
            foreach ($quote->getErrors() as $error) {
                $problems[] = (string) $error->getText();
            }
        }

        return $this->finish($preview, array_values(array_unique($problems)));
    }

    private function fillTotals(OrderPreviewInterface $preview, Quote $quote): void
    {
        $lines = [];
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $line = $this->lineFactory->create();
            $line->setSku((string) $quoteItem->getSku());
            $line->setName((string) $quoteItem->getName());
            $line->setQty((int) $quoteItem->getQty());
            $line->setUnitPrice((float) $quoteItem->getPrice());
            $line->setRowTotal((float) $quoteItem->getRowTotal());
            $lines[] = $line;
        }
        $shipping = $quote->getShippingAddress();
        $preview->setLines($lines);
        $preview->setShippingAmount((float) $shipping->getShippingAmount());
        $preview->setSubtotal((float) $quote->getSubtotal());
        $preview->setTaxAmount((float) $shipping->getTaxAmount());
        $preview->setGrandTotal((float) $quote->getGrandTotal());
        $preview->setCurrency((string) $quote->getQuoteCurrencyCode());
    }

    /** @param string[] $problems */
    private function finish(OrderPreviewInterface $preview, array $problems, ?Quote $quote = null): OrderPreviewInterface
    {
        if ($quote !== null && $preview->getGrandTotal() === null) {
            $this->fillTotals($preview, $quote);
        }
        $preview->setProblems($problems);
        $preview->setOk($problems === []);
        return $preview;
    }

    private function oneLine(\Magento\Customer\Api\Data\AddressInterface $address): string
    {
        $parts = array_merge(
            (array) $address->getStreet(),
            [$address->getCity(), $address->getRegion() ? $address->getRegion()->getRegion() : null,
                $address->getPostcode()]
        );
        return implode(', ', array_filter(array_map('trim', array_map('strval', $parts))));
    }
}
