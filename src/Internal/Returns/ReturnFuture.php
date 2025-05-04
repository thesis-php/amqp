<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Returns;

use Amp\Future;

/**
 * @internal
 */
final class ReturnFuture
{
    /**
     * @param Future<never> $future
     * @param \Closure(): void $complete
     */
    public function __construct(
        public readonly Future $future,
        private readonly \Closure $complete,
    ) {}

    public function complete(): void
    {
        ($this->complete)();
    }
}
