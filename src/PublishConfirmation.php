<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\Future;

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
            $futures[$confirmation->deliveryTag] = $confirmation->future;
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
    ) {
        $this->future->map(function (PublishResult $result): void {
            $this->result = $result;
        });
    }

    public function await(?Cancellation $cancellation = null): PublishResult
    {
        $cancellation?->subscribe($this->cancel(...));

        return $this->future->await($cancellation);
    }

    /**
     * @return Future<PublishResult>
     */
    public function future(): Future
    {
        return $this->future;
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
