<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Revolt\EventLoop;
use Thesis\Amqp\Internal\Concurrent;

/**
 * @api
 */
final class BatchConsumer
{
    public function __construct(
        private readonly Channel $channel,
        private readonly ConsumeBatchOptions $options,
    ) {}

    /**
     * @param callable(ConsumeBatch, Channel): void $callback
     * @param array<string, mixed> $arguments
     * @throws \Throwable
     */
    public function consume(
        callable $callback,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = [],
    ): DeferredCancellation {
        $this->channel->qos(
            prefetchCount: $this->options->count,
            global: $this->options->global,
        );

        $iterator = $this->channel->consumeIterator(
            queue: $queue,
            consumerTag: $consumerTag,
            noLocal: $noLocal,
            noAck: $noAck,
            exclusive: $exclusive,
            noWait: $noWait,
            arguments: $arguments,
        );

        $canceller = new DeferredCancellation();

        EventLoop::queue(
            $this->consumeFromIterator(...),
            $iterator,
            $callback,
            $canceller->getCancellation(),
        );

        return $canceller;
    }

    /**
     * @param callable(ConsumeBatch, Channel): void $callback
     */
    private function consumeFromIterator(
        Iterator $iterator,
        callable $callback,
        Cancellation $cancellation,
    ): void {
        $callbackId = $cancellation->subscribe(static function () use ($iterator): void {
            $iterator->complete();
        });

        try {
            while (!$cancellation->isRequested()) {
                $deliveries = $this->await($iterator, $cancellation);

                if (\count($deliveries) > 0) {
                    $batch = new ConsumeBatch($deliveries);

                    try {
                        $callback($batch, $this->channel);
                        $batch->ack();
                    } catch (\Throwable) {
                        $batch->nack();
                    }
                }
            }
        } catch (CancelledException) {
            // no-op.
        } finally {
            EventLoop::cancel($callbackId);
        }
    }

    /**
     * @return list<DeliveryMessage>
     */
    private function await(Iterator $iterator, Cancellation $cancellation): array
    {
        /** @var Concurrent\Selector<null> $selector */
        $selector = new Concurrent\Selector();

        $selector = $selector->subscribe(
            Concurrent\CancellationFuture::fromCancellation($cancellation),
        );

        if ($this->options->timeout !== null) {
            $selector = $selector->subscribe(
                Concurrent\TimerFuture::delay($this->options->timeout),
            );
        }

        $count = $this->options->count;

        /** @var list<DeliveryMessage> $deliveries */
        $deliveries = [];

        $selector = $selector->subscribe(
            new Concurrent\BarrierFuture($count, static function (callable $advance) use ($iterator, $count, &$deliveries): void {
                foreach ($iterator as $delivery) {
                    $deliveries[] = $delivery;
                    $advance();

                    if (\count($deliveries) === $count) {
                        break;
                    }
                }
            }),
        );

        $selector->select();

        return $deliveries;
    }
}
