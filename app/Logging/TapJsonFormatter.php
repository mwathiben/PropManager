<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Phase-14 OBSERV-7: log channel tap that swaps each handler's
 * formatter to JsonFormatter. Producing JSON makes the logs
 * parseable by Loki / ELK / Datadog without a custom grok pattern.
 *
 * Order matters when stacked with TapMaskingProcessor (Phase-13
 * DPA-6): masking runs first (processor → before formatter), then
 * the JSON formatter emits the already-masked context.
 *
 * Opt-in via LOG_FORMATTER=json env var, mediated by
 * config('logging.formatter'). Local dev keeps the line formatter
 * for human readability.
 */
class TapJsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        $formatter = new JsonFormatter(
            batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
            appendNewline: true,
        );
        $formatter->includeStacktraces(true);

        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'setFormatter')) {
                $handler->setFormatter($formatter);
            }
        }
    }
}
