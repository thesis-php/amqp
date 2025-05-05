<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Amp\DeferredFuture;
use Thesis\Amqp\Internal\Protocol\Frame\BasicAck;
use Thesis\Amqp\Internal\Protocol\Frame\BasicNack;
use Thesis\Amqp\Internal\Returns\ReturnFuture;
use Thesis\Amqp\PublishConfirmation;
use Thesis\Amqp\PublishResult;

/**
 * @internal
 */
final class ConfirmationListener implements \Countable
{
    /** @var non-negative-int */
    private int $deliveryTag = 0;

    /** @var non-negative-int */
    private int $confirmed = 0;

    /** @var array<non-negative-int, DeferredFuture<PublishResult>> */
    private array $confirms = [];

    /**
     * @param non-negative-int $channelId
     */
    public function __construct(
        private readonly Hooks $hooks,
        private readonly int $channelId,
    ) {}

    public function listen(): void
    {
        [$this->deliveryTag, $this->confirmed, $this->confirms] = [0, 0, []];

        $this->hooks->subscribe($this->channelId, BasicAck::class, $this->confirm(PublishResult::Acked));
        $this->hooks->subscribe($this->channelId, BasicNack::class, $this->confirm(PublishResult::Nacked));
    }

    public function newConfirmation(?ReturnFuture $returnFuture = null): PublishConfirmation
    {
        $deliveryTag = ++$this->deliveryTag;

        /** @var DeferredFuture<PublishResult> $deferred */
        $deferred = new DeferredFuture();
        $this->confirms[$deliveryTag] = $deferred;

        return new PublishConfirmation($deliveryTag, $deferred->getFuture(), function () use ($deliveryTag, $deferred): void {
            unset($this->confirms[$deliveryTag]);
            $deferred->complete(PublishResult::Canceled);
        }, $returnFuture);
    }

    public function count(): int
    {
        return \count($this->confirms);
    }

    /**
     * @return callable(BasicAck|BasicNack): void
     */
    private function confirm(PublishResult $result): callable
    {
        return function (BasicAck|BasicNack $frame) use ($result): void {
            if ($frame->multiple) {
                for ($i = $this->confirmed + 1; $i < $frame->deliveryTag; ++$i) {
                    $this->complete($i, $result);
                }
            }

            $this->complete($frame->deliveryTag, $result);
        };
    }

    /**
     * @param non-negative-int $deliveryTag
     */
    private function complete(int $deliveryTag, PublishResult $result): void
    {
        $confirmation = $this->confirms[$deliveryTag] ?? null;
        if ($confirmation !== null) {
            $confirmation->complete($result);
            unset($this->confirms[$deliveryTag]);

            $this->confirmed = $deliveryTag;
        }
    }
}
