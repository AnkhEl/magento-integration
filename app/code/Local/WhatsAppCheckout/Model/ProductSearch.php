<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductSearch
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array<int, array{sku: string, name: string}> */
    public function find(string $term): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->addStoreFilter($this->storeManager->getStore());
        $collection->addAttributeToSelect(['name', 'required_options']);
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => '%' . addcslashes($term, '%_') . '%'],
            ['attribute' => 'sku', 'eq' => $term],
        ]);
        $collection->addAttributeToFilter('type_id', ProductType::TYPE_SIMPLE);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        $collection->setPageSize(20);
        $matches = [];
        foreach ($collection as $product) {
            if ($product->getRequiredOptions() || !$product->isSalable()) {
                continue;
            }
            $matches[] = ['sku' => (string) $product->getSku(), 'name' => (string) $product->getName()];
            if (count($matches) === 5) {
                break;
            }
        }
        return $matches;
    }
}
