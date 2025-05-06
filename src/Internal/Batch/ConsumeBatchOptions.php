<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Batch;

/**
 * @internal
 */
final readonly class ConsumeBatchOptions
{
    /**
     * @param positive-int $count
     * @param ?float $timeout in seconds
     */
    public function __construct(
        public int $count,
        public ?float $timeout = null,
    ) {}
}
