<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Concurrent;

use Amp\DeferredFuture;
use Amp\Future;
use Amp\Sync\Barrier;
use Revolt\EventLoop;

/**
 * @internal
 *
 * @template-implements Awaitable<null>
 */
final class BarrierFuture implements
    Awaitable,
    Cancellable
{
    /** @var Future<null> */
    private readonly Future $future;

    private readonly Barrier $barrier;

    /** @var list<string> */
    private array $callbackIds;

    /**
     * @param positive-int $count
     * @param callable(callable(): void): void $advance
     */
    public function __construct(
        int $count,
        callable $advance,
    ) {
        $barrier = new Barrier($count);
        $this->barrier = $barrier;

        /** @var DeferredFuture<null> $deferred */
        $deferred = new DeferredFuture();
        $this->future = $deferred->getFuture();

        $this->callbackIds[] = EventLoop::defer(static function () use ($barrier, $deferred): void {
            $barrier->await();
            $deferred->complete();
        });

        $this->callbackIds[] = EventLoop::defer(static function () use ($barrier, $advance): void {
            $advance($barrier->arrive(...));
        });
    }

    /**
     * @param positive-int $count
     */
    public function advance(int $count = 1): void
    {
        $this->barrier->arrive($count);
    }

    public function future(): Future
    {
        return $this->future;
    }

    public function cancel(): void
    {
        foreach ($this->callbackIds as $callbackId) {
            EventLoop::cancel($callbackId);
        }
    }
}
