<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Returns;

use Amp\Future;

/**
 * @internal
 */
final readonly class ReturnFuture
{
    /**
     * @param Future<never> $future
     * @param \Closure(): void $complete
     */
    public function __construct(
        public Future $future,
        private \Closure $complete,
    ) {}

    public function complete(): void
    {
        ($this->complete)();
    }
}
