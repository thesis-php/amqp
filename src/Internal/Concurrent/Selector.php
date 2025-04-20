<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Concurrent;

use Amp\Future;

/**
 * @template T = mixed
 */
final class Selector
{
    /** @var list<Awaitable<T>> */
    private array $awaits = [];

    /**
     * @param Awaitable<T> $await
     */
    public function subscribe(Awaitable $await): self
    {
        $selector = clone $this;
        $selector->awaits[] = $await;

        return $selector;
    }

    /**
     * @throws \Throwable
     */
    public function select(): void
    {
        try {
            Future\awaitAny(
                array_map(
                    static fn(Awaitable $awaitable): Future => $awaitable->future(),
                    $this->awaits,
                ),
            );
        } finally {
            foreach ($this->awaits as $awaitable) {
                if ($awaitable instanceof Cancellable) {
                    $awaitable->cancel();
                }
            }
        }
    }
}
