<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Rpc;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use Thesis\Amqp\Channel;
use Thesis\Amqp\ChannelRpc;
use Thesis\Amqp\ChannelRpcConfig;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;

/**
 * @internal
 */
final class Handler implements ChannelRpc
{
    /** @var non-empty-string */
    private readonly string $consumerTag;

    /** @var non-empty-string */
    private readonly string $replyTo;

    /** @var array<non-empty-string, DeferredFuture<Message>> */
    private array $futures = [];

    /**
     * @param \Closure(): void $cancel
     */
    public function __construct(
        private readonly Channel $publishChannel,
        private readonly Channel $consumeChannel,
        private readonly ChannelRpcConfig $config,
        private readonly \Closure $cancel,
    ) {
        $this->replyTo = self::generateReplyTo();

        $this->consumeChannel->queueDeclare(
            queue: $this->replyTo,
            exclusive: true,
            autoDelete: true,
        );

        $this->consumerTag = $this->consumeChannel->consume(
            callback: $this->resolveResponses(...),
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
        $correlationId = self::generateId();

        /** @var DeferredFuture<Message> $deferred */
        $deferred = new DeferredFuture();
        $this->futures[$correlationId] = $deferred;

        $this->publishChannel
            ->publish(
                message: new Message(
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
                ),
                exchange: $exchange,
                routingKey: $routingKey,
                mandatory: $mandatory,
                immediate: $immediate,
            )
            ?->await()
            ->ensurePublished();

        $cancellation ??= new TimeoutCancellation($this->config->timeout);

        return $deferred->getFuture()->await($cancellation);
    }

    public function close(): void
    {
        $this->publishChannel->close();
        $this->consumeChannel->cancel($this->consumerTag);
        ($this->cancel)();
    }

    private function resolveResponses(DeliveryMessage $delivery): void
    {
        if (($correlationId = $delivery->message->correlationId ?: '') !== '') {
            try {
                ($this->futures[$correlationId] ?? null)?->complete($delivery->message);
            } finally {
                unset($this->futures[$correlationId]);
            }
        }
    }

    /**
     * @return non-empty-string
     */
    private static function generateReplyTo(): string
    {
        $id = self::generateId();

        return "thesis.rpc.{$id}";
    }

    /**
     * @return non-empty-string
     */
    private static function generateId(): string
    {
        return bin2hex(random_bytes(10));
    }
}
