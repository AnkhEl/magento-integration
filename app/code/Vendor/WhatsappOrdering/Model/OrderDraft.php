<?php
declare(strict_types=1);

namespace Vendor\WhatsappOrdering\Model;

use Magento\Quote\Model\Quote;
use Vendor\WhatsappOrdering\Api\Data\OrderPreviewInterface;

/** The preview, plus the unsaved quote behind it (only set when the quote got as far as being built). */
final class OrderDraft
{
    public function __construct(
        public readonly OrderPreviewInterface $preview,
        public readonly ?Quote $quote,
        public readonly int $customerId
    ) {
    }
}
