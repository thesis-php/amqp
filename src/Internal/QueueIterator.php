<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Amp\Cancellation;
use Amp\Pipeline;
use Thesis\Amqp\Channel;
use Thesis\Amqp\Iterator;

/**
 * @internal
 * @template T
 * @template-implements Iterator<T>
 */
final class QueueIterator implements Iterator
{
    /**
     * @template E
     * @param non-empty-string $consumerTag
     * @param non-negative-int $size
     * @return self<E>
     * @phpstan-ignore method.templateTypeNotInParameter
     */
    public static function buffered(
        string $consumerTag,
        Channel $channel,
        int $size,
    ): self {
        /** @var Pipeline\Queue<E> $queue */
        $queue = new Pipeline\Queue(bufferSize: $size);

        return new self($queue, $channel, $consumerTag);
    }

    /** @var Pipeline\ConcurrentIterator<T> */
    private readonly Pipeline\ConcurrentIterator $iterator;

    /**
     * @param Pipeline\Queue<T> $queue
     * @param non-empty-string $consumerTag
     */
    private function __construct(
        private readonly Pipeline\Queue $queue,
        private readonly Channel $channel,
        private readonly string $consumerTag,
    ) {
        $this->iterator = $this->queue->iterate();
    }

    public function push(mixed $delivery): void
    {
        $this->queue->push($delivery);
    }

    public function complete(bool $noWait = false): void
    {
        $this->channel->cancel($this->consumerTag, $noWait);
        $this->queue->complete();
    }

    public function cancel(\Throwable $e, bool $noWait = false): void
    {
        $this->channel->cancel($this->consumerTag, $noWait);
        $this->queue->error($e);
    }

    public function continue(?Cancellation $cancellation = null): bool
    {
        return $this->iterator->continue($cancellation);
    }

    public function value(): mixed
    {
        return $this->iterator->getValue();
    }

    public function getIterator(): \Traversable
    {
        yield from $this->iterator;
    }
}
