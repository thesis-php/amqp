<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Pipeline;

/**
 * @api
 * @template-implements \IteratorAggregate<array-key, DeliveryMessage>
 */
final class Iterator implements \IteratorAggregate
{
    /**
     * @internal
     * @param non-empty-string $consumerTag
     * @param non-negative-int $size
     */
    public static function buffered(string $consumerTag, Channel $channel, int $size): self
    {
        /** @var Pipeline\Queue<DeliveryMessage> $queue */
        $queue = new Pipeline\Queue(bufferSize: $size);

        return new self($queue, $channel, $consumerTag);
    }

    /** @var Pipeline\ConcurrentIterator<DeliveryMessage> */
    private readonly Pipeline\ConcurrentIterator $iterator;

    /**
     * @internal
     * @param Pipeline\Queue<DeliveryMessage> $queue
     * @param non-empty-string $consumerTag
     */
    private function __construct(
        private readonly Pipeline\Queue $queue,
        private readonly Channel $channel,
        private readonly string $consumerTag,
    ) {
        $this->iterator = $this->queue->iterate();
    }

    /**
     * @internal
     */
    public function push(DeliveryMessage $delivery): void
    {
        $this->queue->push($delivery);
    }

    /**
     * @throws \Throwable
     */
    public function complete(bool $noWait = false): void
    {
        $this->channel->cancel($this->consumerTag, $noWait);
        $this->queue->complete();
    }

    /**
     * @throws \Throwable
     */
    public function cancel(\Throwable $e, bool $noWait = false): void
    {
        $this->channel->cancel($this->consumerTag, $noWait);
        $this->queue->error($e);
    }

    public function getIterator(): \Traversable
    {
        foreach ($this->iterator as $delivery) {
            yield $delivery;
        }
    }
}
