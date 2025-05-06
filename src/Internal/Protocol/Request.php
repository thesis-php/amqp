<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

/**
 * @internal
 */
final readonly class Request
{
    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        public int $channelId,
        public Frame $frame,
    ) {}
}
