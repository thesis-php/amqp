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
    private ?Sync\Once $connection = null;

    /** @var ?Sync\Once<void> */
    private ?Sync\Once $disconnection = null;

    private ?Client $consumerClient = null;

    /** @var non-empty-string */
    private readonly string $replyTo;

    /** @var array<non-empty-string, DeferredFuture<Message>> */
    private array $futures = [];

    public function __construct(
        private readonly Client $client,
        private readonly RpcConfig $config = new RpcConfig(),
    ) {
        $this->replyTo = self::generateReplyTo();
    }

    public function request(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
        ?Cancellation $cancellation = null,
    ): Message {
        $publishChannel = $this->channel($cancellation);

        $correlationId = $message->correlationId ?: self::generateId();

        /** @var ?DeferredFuture<Message> $deferred */
        $deferred = $this->futures[$correlationId] ?? null;

        if ($deferred === null) {
            /** @var DeferredFuture<Message> $deferred */
            $deferred = new DeferredFuture();
            $this->futures[$correlationId] = $deferred;

            $publishChannel
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
        $this->connection?->await($cancellation);

        try {
            ($this->disconnection ??= new Sync\Once(weakClosure($this->shutdown(...))))->await($cancellation);
        } finally {
            $this->disconnection = null;
            $this->connection = null;
        }
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

    private function channel(?Cancellation $cancellation = null): Channel
    {
        return ($this->connection ??= new Sync\Once(weakClosure($this->setup(...))))->await($cancellation);
    }

    private function setup(): Channel
    {
        $publishChannel = $this->client->channel();

        if ($this->config->confirms) {
            $publishChannel->confirmSelect();
        }

        $this->consumerClient = new Client($this->client->config);
        $consumeChannel = $this->consumerClient->channel();

        $consumeChannel->queueDeclare(
            queue: $this->replyTo,
            exclusive: true,
            autoDelete: true,
        );

        $consumeChannel->consume(
            callback: function (DeliveryMessage $delivery): void {
                ($this->futures[$delivery->message->correlationId ?? ''] ?? null)?->complete($delivery->message);
            },
            queue: $this->replyTo,
            noAck: true,
        );

        return $publishChannel;
    }

    private function shutdown(): void
    {
        $this->connection?->await()->close();
        $this->consumerClient?->disconnect();
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
