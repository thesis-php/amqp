<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use Thesis\Amqp\Internal\Rpc;

/**
 * @api
 */
final class RpcHandler
{
    /** @var non-empty-string */
    private readonly string $replyTo;

    /** @var array<non-empty-string, DeferredFuture<Message>> */
    private array $futures = [];

    /**
     * @internal
     * @param \Closure(): void $cancel
     */
    public function __construct(
        private readonly Channel $publishChannel,
        private readonly Channel $consumeChannel,
        private readonly RpcConfig $config,
        private readonly \Closure $cancel,
    ) {
        $this->replyTo = Rpc\generateReplyTo();

        $this->consumeChannel->queueDeclare(
            queue: $this->replyTo,
            exclusive: true,
            autoDelete: true,
        );

        $this->consumeChannel->consume(
            callback: function (DeliveryMessage $delivery): void {
                ($this->futures[$delivery->message->correlationId ?: ''] ?? null)?->complete($delivery->message);
            },
            queue: $this->replyTo,
            noAck: true,
        );
    }

    public function request(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?Cancellation $cancellation = null,
    ): Message {
        $correlationId = $message->correlationId ?: Rpc\generateId();

        /** @var ?DeferredFuture<Message> $deferred */
        $deferred = $this->futures[$correlationId] ?? null;

        if ($deferred === null) {
            /** @var DeferredFuture<Message> $deferred */
            $deferred = new DeferredFuture();
            $this->futures[$correlationId] = $deferred;

            $this->publishChannel
                ->publish(
                    message: $this->createMessage($message, $correlationId),
                    exchange: $exchange,
                    routingKey: $routingKey,
                    mandatory: $mandatory,
                    immediate: $immediate,
                )
                ?->await()
                ->ensurePublished();
        }

        $cancellation ??= new TimeoutCancellation($this->config->timeout->toSeconds());

        try {
            return $deferred->getFuture()->await($cancellation);
        } finally {
            unset($this->futures[$correlationId]);
        }
    }

    public function close(): void
    {
        $this->publishChannel->close();
        ($this->cancel)();
    }

    private function createMessage(Message $message, string $correlationId): Message
    {
        return new Message(
            body: $message->body,
            headers: $message->headers,
            contentType: $message->contentType,
            contentEncoding: $message->contentEncoding,
            deliveryMode: $message->deliveryMode,
            priority: $message->priority,
            correlationId: $correlationId,
            replyTo: $this->replyTo,
            expiration: $message->expiration,
            messageId: $message->messageId,
            timestamp: $message->timestamp,
            type: $message->type,
            userId: $message->userId,
            appId: $message->appId,
        );
    }
}
