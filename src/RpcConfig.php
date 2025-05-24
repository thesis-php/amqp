<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Thesis\Time\TimeSpan;

/**
 * @api
 */
final readonly class RpcConfig
{
    private const int DEFAULT_TIMEOUT = 2;

    public TimeSpan $timeout;

    /**
     * @param ?non-empty-string $replyTo temporary queue name, must be unique for all instances of the application
     */
    public function __construct(
        public bool $confirms = true,
        public ?string $replyTo = null,
        ?TimeSpan $timeout = null,
    ) {
        $this->timeout = $timeout ?? TimeSpan::fromSeconds(self::DEFAULT_TIMEOUT);
    }
}
