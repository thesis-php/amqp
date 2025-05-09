<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 */
final readonly class RpcConfig
{
    /**
     * @param float $timeout in seconds
     */
    public function __construct(
        public bool $confirms = true,
        public float $timeout = 2,
    ) {}
}
