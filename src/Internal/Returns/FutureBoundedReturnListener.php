<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Returns;

use Amp\DeferredFuture;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Exception\MessageCannotBeRouted;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;
use Thesis\Amqp\Message;

/**
 * @internal
 */
final class FutureBoundedReturnListener
{
    public const TRACE_HEADER_KEY = 'X-Thesis-Mandatory-Id';

    /** @var array<non-empty-string, DeferredFuture<never>> */
    private array $futures = [];

    /** @var \Closure(): non-empty-string */
    private readonly \Closure $mandatoryIdGenerator;

    /**
     * @param ?\Closure(): non-empty-string $mandatoryIdGenerator
     */
    public function __construct(
        private readonly DeliverySupervisor $supervisor,
        ?\Closure $mandatoryIdGenerator = null,
    ) {
        $this->mandatoryIdGenerator = $mandatoryIdGenerator ?: static fn(): string => bin2hex(random_bytes(10));
    }

    public function listen(): void
    {
        $futures = &$this->futures;

        $this->supervisor->addReturnListener(static function (DeliveryMessage $delivery) use (&$futures): void {
            if (($correlationId = ($delivery->message->headers[self::TRACE_HEADER_KEY] ?? null)) !== null) {
                try {
                    ($futures[$correlationId] ?? null)?->error(new MessageCannotBeRouted());
                } finally {
                    unset($futures[$correlationId]);
                }
            }
        });

        $this->supervisor->addShutdownListener(static function () use (&$futures): void {
            $futures = [];
        });
    }

    /**
     * @return array{Message, ReturnFuture}
     */
    public function trace(Message $message): array
    {
        $uniqueId = ($this->mandatoryIdGenerator)();

        /** @var DeferredFuture<never> $deferred */
        $deferred = new DeferredFuture();
        $this->futures[$uniqueId] = $deferred;

        $message = new Message(
            body: $message->body,
            headers: array_merge($message->headers, [self::TRACE_HEADER_KEY => $uniqueId]),
            contentType: $message->contentType,
            contentEncoding: $message->contentEncoding,
            deliveryMode: $message->deliveryMode,
            priority: $message->priority,
            correlationId: $message->correlationId,
            replyTo: $message->replyTo,
            expiration: $message->expiration,
            messageId: $message->messageId,
            timestamp: $message->timestamp,
            type: $message->type,
            userId: $message->userId,
            appId: $message->appId,
        );

        $returnFuture = new ReturnFuture(
            $deferred->getFuture(),
            function () use ($uniqueId): void {
                unset($this->futures[$uniqueId]);
            },
        );

        return [$message, $returnFuture];
    }
}
