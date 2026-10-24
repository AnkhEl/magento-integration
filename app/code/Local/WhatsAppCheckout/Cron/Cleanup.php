<?php

declare(strict_types=1);

namespace Local\WhatsAppCheckout\Cron;

use Local\WhatsAppCheckout\Model\Storage;

class Cleanup
{
    public function __construct(private readonly Storage $storage)
    {
    }

    public function execute(): void
    {
        $this->storage->cleanup();
    }
}
