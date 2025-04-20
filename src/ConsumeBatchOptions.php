<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final class ConsumeBatchOptions
{
    /**
     * @param positive-int $count
     * @param ?float $timeout in seconds
     */
    public function __construct(
        public readonly int $count,
        public readonly ?float $timeout = null,
        public readonly bool $global = false,
    ) {}
}
