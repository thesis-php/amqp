<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\Future;
use Thesis\Amqp\Exception\MessageCannotBeRouted;
use Thesis\Amqp\Internal\Returns\ReturnFuture;
use function Amp\async;

/**
 * @api
 */
final class PublishConfirmation
{
    /**
     * @param iterable<self> $confirmations
     * @return array<non-negative-int, PublishResult>
     */
    public static function awaitAll(iterable $confirmations, ?Cancellation $cancellation = null): array
    {
        $publishResults = [];

        foreach (self::iterate($confirmations, $cancellation) as $deliveryTag => $future) {
            $publishResults[$deliveryTag] = $future->await($cancellation);
        }

        return $publishResults;
    }

    /**
     * @param iterable<self> $confirmations
     * @return iterable<non-negative-int, Future<PublishResult>>
     */
    public static function iterate(iterable $confirmations, ?Cancellation $cancellation = null): iterable
    {
        $futures = [];
        foreach ($confirmations as $confirmation) {
            $futures[$confirmation->deliveryTag] = async($confirmation->await(...));
        }

        return Future::iterate($futures, $cancellation);
    }

    private PublishResult $result = PublishResult::Waiting;

    /**
     * @param non-negative-int $deliveryTag
     * @param Future<PublishResult> $future
     * @param \Closure(): void $cancel
     */
    public function __construct(
        public readonly int $deliveryTag,
        private readonly Future $future,
        private readonly \Closure $cancel,
        private readonly ?ReturnFuture $returnFuture = null,
    ) {}

    public function await(?Cancellation $cancellation = null): PublishResult
    {
        if ($this->result !== PublishResult::Waiting) {
            return $this->result;
        }

        $futures = [$this->future];

        if ($this->returnFuture !== null) {
            $futures[] = $this->returnFuture->future;
        }

        $cancellationId = $cancellation?->subscribe($this->cancel(...));

        try {
            return $this->result = Future\awaitFirst($futures, $cancellation);
        } catch (MessageCannotBeRouted) { // @phpstan-ignore-line
            return $this->result = PublishResult::Unrouted;
        } finally {
            /** @phpstan-ignore argument.type */
            $cancellation?->unsubscribe($cancellationId);
            $this->returnFuture?->complete();
        }
    }

    public function result(): PublishResult
    {
        return $this->result;
    }

    public function cancel(): void
    {
        ($this->cancel)();
    }
}
