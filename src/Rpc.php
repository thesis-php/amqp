<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use Thesis\Sync;
use function Amp\weakClosure;

/**
 * @api
 */
final class Rpc
{
    /** @var ?Sync\Once<Channel> */
    private ?Sync\Once $channel = null;

    /** @var non-empty-string */
    private string $replyTo;

    /** @var array<non-empty-string, DeferredFuture<Message>> */
    private array $futures = [];

    public function __construct(
        private readonly Client $client,
        private readonly RpcConfig $config = new RpcConfig(),
    ) {}

    public function request(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?Cancellation $cancellation = null,
    ): Message {
        $channel = $this->channel($cancellation);

        $correlationId = $message->correlationId ?? self::generateId();

        /** @var ?DeferredFuture<Message> $deferred */
        $deferred = $this->futures[$correlationId] ?? null;

        if ($deferred === null) {
            /** @var DeferredFuture<Message> $deferred */
            $deferred = new DeferredFuture();
            $this->futures[$correlationId] = $deferred;

            $channel
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

    public function close(?Cancellation $cancellation = null): void
    {
        [$channel, $this->channel] = [$this->channel, null];
        $channel?->await($cancellation)->close(cancellation: $cancellation);
    }

    /**
     * @param non-empty-string $correlationId
     */
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

    private function channel(?Cancellation $cancellation = null): Channel
    {
        return ($this->channel ??= new Sync\Once(weakClosure($this->setup(...)), static fn(Channel $channel): bool => !$channel->isClosed()))->await($cancellation);
    }

    private function setup(): Channel
    {
        $channel = $this->client->channel();

        if ($this->config->confirms) {
            $channel->confirmSelect();
        }

        $this->replyTo = $channel
            ->queueDeclare(
                queue: ($this->config->generateReplyTo ?? self::generateReplyTo(...))(),
                exclusive: true,
                autoDelete: true,
            )
            ->name;

        $channel->consume(
            callback: function (DeliveryMessage $delivery): void {
                ($this->futures[$delivery->message->correlationId ?? ''] ?? null)?->complete($delivery->message);
            },
            queue: $this->replyTo,
            noAck: true,
        );

        return $channel;
    }

    /**
     * @return non-empty-string
     */
    private static function generateReplyTo(): string
    {
        return 'thesis.rpc.' . self::generateId();
    }

    /**
     * @return non-empty-string
     */
    private static function generateId(): string
    {
        return bin2hex(random_bytes(length: 15));
    }
}
