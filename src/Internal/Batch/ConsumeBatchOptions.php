<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Batch;

/**
 * @internal
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
    ) {}
}
