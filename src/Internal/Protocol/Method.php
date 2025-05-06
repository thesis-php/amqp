<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Auth\Mechanism;
use Thesis\Endian\endian;

/**
 * @internal
 */
final readonly class Method implements Frame
{
    /**
     * @param array<string, mixed> $clientProperties
     */
    public static function connectionStartOk(
        array $clientProperties,
        Mechanism $auth,
        string $locale = 'en_US',
    ): self {
        return new self(
            ClassType::CONNECTION,
            ClassMethod::CONNECTION_START_OK,
            new Frame\ConnectionStartOk(
                $clientProperties,
                $auth,
                $locale,
            ),
        );
    }

    /**
     * @param non-negative-int $channelMax
     * @param non-negative-int $frameMax
     * @param non-negative-int $heartbeat
     */
    public static function connectionTuneOk(
        int $channelMax,
        int $frameMax,
        int $heartbeat,
    ): self {
        return new self(
            ClassType::CONNECTION,
            ClassMethod::CONNECTION_TUNE_OK,
            new Frame\ConnectionTuneOk(
                $channelMax,
                $frameMax,
                $heartbeat,
            ),
        );
    }

    /**
     * @param non-empty-string $vhost
     */
    public static function connectionOpen(string $vhost): self
    {
        return new self(
            ClassType::CONNECTION,
            ClassMethod::CONNECTION_OPEN,
            new Frame\ConnectionOpen($vhost),
        );
    }

    /**
     * @param non-negative-int $replyCode
     */
    public static function connectionClose(
        int $replyCode = 200,
        string $replyText = '',
    ): self {
        return new self(
            ClassType::CONNECTION,
            ClassMethod::CONNECTION_CLOSE,
            new Frame\ConnectionClose($replyCode, $replyText),
        );
    }

    public static function connectionCloseOk(): self
    {
        return new self(
            ClassType::CONNECTION,
            ClassMethod::CONNECTION_CLOSE_OK,
            Frame\ConnectionCloseOk::frame,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function channelOpen(int $channelId): self
    {
        return new self(
            ClassType::CHANNEL,
            ClassMethod::CHANNEL_OPEN,
            new Frame\ChannelOpen(),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-negative-int $replyCode
     */
    public static function channelClose(
        int $channelId,
        int $replyCode = 200,
        string $replyText = '',
    ): self {
        return new self(
            ClassType::CHANNEL,
            ClassMethod::CHANNEL_CLOSE,
            new Frame\ChannelClose(
                $replyCode,
                $replyText,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function channelCloseOk(int $channelId): self
    {
        return new self(
            ClassType::CHANNEL,
            ClassMethod::CHANNEL_CLOSE_OK,
            Frame\ChannelCloseOk::frame,
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function channelFlow(int $channelId, bool $active): self
    {
        return new self(
            ClassType::CHANNEL,
            ClassMethod::CHANNEL_FLOW,
            new Frame\ChannelFlow($active),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $exchange
     * @param non-empty-string $exchangeType
     * @param array<string, mixed> $arguments
     */
    public static function exchangeDeclare(
        int $channelId,
        string $exchange,
        string $exchangeType,
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $noWait = false,
        array $arguments = [],
    ): self {
        return new self(
            ClassType::EXCHANGE,
            ClassMethod::EXCHANGE_DECLARE,
            new Frame\ExchangeDeclare(
                exchange: $exchange,
                exchangeType: $exchangeType,
                passive: $passive,
                durable: $durable,
                autoDelete: $autoDelete,
                internal: $internal,
                noWait: $noWait,
                arguments: $arguments,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $destination
     * @param non-empty-string $source
     * @param array<string, mixed> $arguments
     */
    public static function exchangeBind(
        int $channelId,
        string $destination,
        string $source,
        string $routingKey,
        array $arguments = [],
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::EXCHANGE,
            ClassMethod::EXCHANGE_BIND,
            new Frame\ExchangeBind(
                destination: $destination,
                source: $source,
                routingKey: $routingKey,
                arguments: $arguments,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $destination
     * @param non-empty-string $source
     * @param array<string, mixed> $arguments
     */
    public static function exchangeUnbind(
        int $channelId,
        string $destination,
        string $source,
        string $routingKey,
        array $arguments = [],
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::EXCHANGE,
            ClassMethod::EXCHANGE_UNBIND,
            new Frame\ExchangeUnbind(
                destination: $destination,
                source: $source,
                routingKey: $routingKey,
                arguments: $arguments,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $exchange
     */
    public static function exchangeDelete(
        int $channelId,
        string $exchange,
        bool $ifUnused = false,
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::EXCHANGE,
            ClassMethod::EXCHANGE_DELETE,
            new Frame\ExchangeDelete(
                exchange: $exchange,
                ifUnused: $ifUnused,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param array<string, mixed> $arguments
     */
    public static function queueDeclare(
        int $channelId,
        string $queue,
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $noWait = false,
        array $arguments = [],
    ): self {
        return new self(
            ClassType::QUEUE,
            ClassMethod::QUEUE_DECLARE,
            new Frame\QueueDeclare(
                queue: $queue,
                passive: $passive,
                durable: $durable,
                exclusive: $exclusive,
                autoDelete: $autoDelete,
                noWait: $noWait,
                arguments: $arguments,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param array<string, mixed> $arguments
     */
    public static function queueBind(
        int $channelId,
        string $queue,
        string $exchange,
        string $routingKey,
        array $arguments = [],
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::QUEUE,
            ClassMethod::QUEUE_BIND,
            new Frame\QueueBind(
                queue: $queue,
                exchange: $exchange,
                routingKey: $routingKey,
                arguments: $arguments,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $queue
     * @param array<string, mixed> $arguments
     */
    public static function queueUnbind(
        int $channelId,
        string $queue,
        string $exchange,
        string $routingKey,
        array $arguments = [],
    ): self {
        return new self(
            ClassType::QUEUE,
            ClassMethod::QUEUE_UNBIND,
            new Frame\QueueUnbind(
                queue: $queue,
                exchange: $exchange,
                routingKey: $routingKey,
                arguments: $arguments,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $queue
     */
    public static function queuePurge(
        int $channelId,
        string $queue,
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::QUEUE,
            ClassMethod::QUEUE_PURGE,
            new Frame\QueuePurge($queue, $noWait),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $queue
     */
    public static function queueDelete(
        int $channelId,
        string $queue,
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::QUEUE,
            ClassMethod::QUEUE_DELETE,
            new Frame\QueueDelete(
                queue: $queue,
                ifUnused: $ifUnused,
                ifEmpty: $ifEmpty,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function txSelect(int $channelId): self
    {
        return new self(
            ClassType::TX,
            ClassMethod::TX_SELECT,
            Frame\TxSelect::frame,
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function txCommit(int $channelId): self
    {
        return new self(
            ClassType::TX,
            ClassMethod::TX_COMMIT,
            Frame\TxCommit::frame,
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function txRollback(int $channelId): self
    {
        return new self(
            ClassType::TX,
            ClassMethod::TX_ROLLBACK,
            Frame\TxRollback::frame,
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function confirmSelect(int $channelId, bool $noWait = false): self
    {
        return new self(
            ClassType::CONFIRM,
            ClassMethod::CONFIRM_SELECT,
            new Frame\ConfirmSelect($noWait),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function basicPublish(
        int $channelId,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_PUBLISH,
            new Frame\BasicPublish(
                exchange: $exchange,
                routingKey: $routingKey,
                mandatory: $mandatory,
                immediate: $immediate,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function basicGet(
        int $channelId,
        string $queue = '',
        bool $noAck = false,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_GET,
            new Frame\BasicGet(
                queue: $queue,
                noAck: $noAck,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-negative-int $deliveryTag
     */
    public static function basicAck(
        int $channelId,
        int $deliveryTag,
        bool $multiple = false,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_ACK,
            new Frame\BasicAck(
                deliveryTag: $deliveryTag,
                multiple: $multiple,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-negative-int $deliveryTag
     */
    public static function basicNack(
        int $channelId,
        int $deliveryTag,
        bool $multiple = false,
        bool $requeue = true,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_NACK,
            new Frame\BasicNack(
                deliveryTag: $deliveryTag,
                multiple: $multiple,
                requeue: $requeue,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-negative-int $deliveryTag
     */
    public static function basicReject(
        int $channelId,
        int $deliveryTag,
        bool $requeue = true,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_REJECT,
            new Frame\BasicReject(
                deliveryTag: $deliveryTag,
                requeue: $requeue,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     */
    public static function basicRecover(
        int $channelId,
        bool $requeue = false,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_RECOVER,
            new Frame\BasicRecover(
                requeue: $requeue,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-negative-int $prefetchSize
     * @param non-negative-int $prefetchCount
     */
    public static function basicQos(
        int $channelId,
        int $prefetchSize,
        int $prefetchCount,
        bool $global,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_QOS,
            new Frame\BasicQos(
                prefetchSize: $prefetchSize,
                prefetchCount: $prefetchCount,
                global: $global,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param array<string, mixed> $arguments
     */
    public static function basicConsume(
        int $channelId,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $noWait = false,
        array $arguments = [],
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_CONSUME,
            new Frame\BasicConsume(
                queue: $queue,
                consumerTag: $consumerTag,
                noLocal: $noLocal,
                noAck: $noAck,
                exclusive: $exclusive,
                noWait: $noWait,
                arguments: $arguments,
            ),
            $channelId,
        );
    }

    /**
     * @param non-negative-int $channelId
     * @param non-empty-string $consumerTag
     */
    public static function basicCancel(
        int $channelId,
        string $consumerTag,
        bool $noWait = false,
    ): self {
        return new self(
            ClassType::BASIC,
            ClassMethod::BASIC_CANCEL,
            new Frame\BasicCancel(
                consumerTag: $consumerTag,
                noWait: $noWait,
            ),
            $channelId,
        );
    }

    public static function read(Io\ReadBytes $reader): self
    {
        throw new \BadMethodCallException('Not implemented yet.');
    }

    /**
     * @param ClassType::* $classType
     * @param ClassMethod::* $classMethod
     * @param non-negative-int $channelId all connection frames go through zero channel, so it will be the default channel
     */
    private function __construct(
        public int $classType,
        public int $classMethod,
        public Frame $frame,
        public int $channelId = 0,
    ) {}

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint8(FrameType::method->value)
            ->writeUint16($this->channelId)
            ->reserve(endian::network->packUint32(...), function (Io\WriteBytes $writer): void {
                $writer
                    ->writeUint16($this->classType)
                    ->writeUint16($this->classMethod);

                $this->frame->write($writer);
            })
            ->writeUint8(Protocol::FRAME_END);
    }
}
