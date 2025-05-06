<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final readonly class Queue
{
    /**
     * @param non-empty-string $name
     * @param non-negative-int $messages
     * @param non-negative-int $consumers
     */
    public function __construct(
        public string $name,
        public int $messages,
        public int $consumers,
    ) {}
}
