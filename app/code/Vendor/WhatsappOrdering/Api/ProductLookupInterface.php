<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Api;

/**
 * Catalogue questions asked by the messaging middleware (protected by Magento Integration tokens).
 *
 * @api
 */
interface ProductLookupInterface
{
    /**
     * Check SKUs a customer typed: does each exist, what is it called, what does it cost, and can it be ordered?
     * One answer per requested SKU, in the same order. A SKU that cannot be ordered is described, not an error.
     *
     * @param string[] $skus Between 1 and 20 SKUs as typed by the customer
     * @return \Vendor\WhatsappOrdering\Api\Data\ProductInfoInterface[]
     * @throws \Magento\Framework\Exception\InputException When the list is empty or longer than 20
     */
    public function lookup($skus);
}
