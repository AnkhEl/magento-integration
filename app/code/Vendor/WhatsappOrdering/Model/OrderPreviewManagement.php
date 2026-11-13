<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterface;
use Vendor\WhatsappOrdering\Api\OrderPreviewManagementInterface;

/** Shows what an order would look like. Creates nothing: the quote built by the draft builder is simply dropped. */
class OrderPreviewManagement implements OrderPreviewManagementInterface
{
    public function __construct(
        private readonly OrderDraftBuilder $builder
    ) {
    }

    public function preview($phone, $items): OrderPreviewInterface
    {
        return $this->builder->build($phone, $items)->preview;
    }
}
