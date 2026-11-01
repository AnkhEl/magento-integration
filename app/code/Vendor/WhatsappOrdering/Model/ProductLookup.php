<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\WhatsappOrdering\Api\Data\ProductInfoInterface;
use Vendor\WhatsappOrdering\Api\Data\ProductInfoInterfaceFactory;
use Vendor\WhatsappOrdering\Api\ProductLookupInterface;

/**
 * Answers "can this SKU be ordered?" for the messaging middleware.
 *
 * Everything is judged for the store's default store view and website, which is what a REST call without a store
 * code uses. Stock comes from the Inventory (MSI) salable quantity for that website's stock, never from the old
 * cataloginventory tables. Only simple products can be ordered by SKU for now.
 */
class ProductLookup implements ProductLookupInterface
{
    public const MAX_SKUS = 20;
    private const MAX_SKU_LENGTH = 64;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly IsProductSalableInterface $isProductSalable,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly ProductInfoInterfaceFactory $productInfoFactory
    ) {
    }

    public function lookup($skus)
    {
        if (!is_array($skus) || $skus === []) {
            throw new InputException(__('Send at least one SKU.'));
        }
        if (count($skus) > self::MAX_SKUS) {
            throw new InputException(__('Send at most %1 SKUs at a time.', self::MAX_SKUS));
        }

        $store = $this->storeManager->getStore();
        $website = $this->storeManager->getWebsite();
        $stockId = (int) $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, $website->getCode())
            ->getStockId();
        $currency = (string) $store->getBaseCurrencyCode();

        $answers = [];
        foreach ($skus as $requested) {
            $answers[] = $this->describe(
                trim((string) $requested),
                (int) $store->getId(),
                (int) $website->getId(),
                $stockId,
                $currency
            );
        }

        return $answers;
    }

    private function describe(string $sku, int $storeId, int $websiteId, int $stockId, string $currency): ProductInfoInterface
    {
        /** @var ProductInfoInterface $info */
        $info = $this->productInfoFactory->create();
        $info->setRequestedSku($sku)->setFound(false)->setOrderable(false);

        if ($sku === '' || strlen($sku) > self::MAX_SKU_LENGTH) {
            return $info;
        }

        try {
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException) {
            return $info;
        }

        $info->setFound(true)
            ->setSku((string) $product->getSku())
            ->setName((string) $product->getName())
            ->setPrice($this->price($product))
            ->setCurrency($currency);

        $salableQty = $this->salableQty((string) $product->getSku(), $stockId);
        $info->setSalableQty($salableQty);

        $reason = $this->reasonNotOrderable($product, $websiteId, $stockId);
        $info->setReason($reason)->setOrderable($reason === null);

        return $info;
    }

    /** Null when the product can be ordered, otherwise a short code the middleware can turn into words. */
    private function reasonNotOrderable(ProductInterface $product, int $websiteId, int $stockId): ?string
    {
        if ($product->getTypeId() !== Type::TYPE_SIMPLE) {
            return 'unsupported_type';
        }
        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            return 'disabled';
        }
        if (!in_array((string) $websiteId, array_map('strval', (array) $product->getWebsiteIds()), true)) {
            return 'not_on_website';
        }
        try {
            $salable = $this->isProductSalable->execute((string) $product->getSku(), $stockId);
        } catch (LocalizedException) {
            // For example a product with no stock record for this website: not something we can sell.
            $salable = false;
        }

        return $salable ? null : 'out_of_stock';
    }

    private function salableQty(string $sku, int $stockId): float
    {
        try {
            return max(0.0, (float) $this->getProductSalableQty->execute($sku, $stockId));
        } catch (LocalizedException) {
            return 0.0;
        }
    }

    /** The price a visitor sees on the storefront (special prices included, tax shown as the store is set up to show it). */
    private function price(ProductInterface $product): float
    {
        return round((float) $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue(), 2);
    }
}
