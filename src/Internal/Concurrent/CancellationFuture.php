<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Concurrent;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Future;

/**
 * @internal
 *
 * @template-implements Awaitable<null>
 */
final class CancellationFuture implements
    Awaitable,
    Cancellable
{
    public static function fromCancellation(Cancellation $cancellation): self
    {
        /** @var DeferredFuture<null> $deferred */
        $deferred = new DeferredFuture();
        $callbackId = $cancellation->subscribe($deferred->complete(...));

        return new self(
            $deferred->getFuture(),
            static function () use ($cancellation, $callbackId): void {
                $cancellation->unsubscribe($callbackId);
            },
        );
    }

    /**
     * @param Future<null> $future
     * @param callable(): void $cancel
     */
    private function __construct(
        private readonly Future $future,
        private readonly mixed $cancel,
    ) {}

    public function future(): Future
    {
        return $this->future;
    }

    public function cancel(): void
    {
        ($this->cancel)();
    }
}
