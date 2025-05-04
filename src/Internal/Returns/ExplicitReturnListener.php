<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Returns;

use Amp\DeferredFuture;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Exception\MessageCannotBeRouted;
use Thesis\Amqp\Message;

/**
 * @internal
 */
final class ExplicitReturnListener
{
    private const TRACE_HEADER_KEY = 'X-Thesis-Correlation-Id';

    /** @var array<non-empty-string, DeferredFuture<never>> */
    private array $futures = [];

    public function __construct(
        private readonly ReturnListener $returns,
    ) {}

    public function listen(): void
    {
        $futures = &$this->futures;

        $this->returns->addReturnCallback(static function (DeliveryMessage $delivery) use (&$futures): void {
            if (($correlationId = ($delivery->message->headers[self::TRACE_HEADER_KEY] ?? null)) !== null) {
                try {
                    ($futures[$correlationId] ?? null)?->error(new MessageCannotBeRouted());
                } finally {
                    unset($futures[$correlationId]);
                }
            }
        });
    }

    public function trace(Message $message): ReturnFuture
    {
        /** @var non-empty-string $uniqueId */
        $uniqueId = bin2hex(random_bytes(20));

        /** @var DeferredFuture<never> $deferred */
        $deferred = new DeferredFuture();
        $this->futures[$uniqueId] = $deferred;

        $message->headers[self::TRACE_HEADER_KEY] = $uniqueId;

        return new ReturnFuture(
            $deferred->getFuture(),
            function () use ($uniqueId): void {
                unset($this->futures[$uniqueId]);
            },
        );
    }
}
