<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Phase-13 DPA-6: log channel tap that pushes
 * SensitiveDataMaskingProcessor onto every Monolog handler. Wired
 * via config/logging.php on each channel where we want masking
 * (single, stack, security, schedule). The tap-on-each-handler
 * pattern is the Laravel idiom for global Monolog processors.
 */
class TapMaskingProcessor
{
    public function __invoke(Logger $logger): void
    {
        $processor = new SensitiveDataMaskingProcessor;
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor($processor);
        }
    }
}
