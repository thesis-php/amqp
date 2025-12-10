<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

/**
 * @internal
 */
final class Properties
{
    /**
     * @param non-negative-int $channelMax
     * @param positive-int $frameMax
     */
    public function __construct(
        public int $channelMax = 0xFFFF,
        public int $frameMax = 0xFFFF,
    ) {}
}
