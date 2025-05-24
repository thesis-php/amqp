<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Thesis\Amqp\DeliveryMode;
use Thesis\Amqp\Message;
use Thesis\Time\TimeSpan;

/**
 * @internal
 */
final readonly class MessageProperties
{
    private const int FLAG_CONTENT_TYPE = 0x8000;
    private const int FLAG_CONTENT_ENCODING = 0x4000;
    private const int FLAG_HEADERS = 0x2000;
    private const int FLAG_DELIVERY_MODE = 0x1000;
    private const int FLAG_PRIORITY = 0x0800;
    private const int FLAG_CORRELATION_ID = 0x0400;
    private const int FLAG_REPLY_TO = 0x0200;
    private const int FLAG_EXPIRATION = 0x0100;
    private const int FLAG_MESSAGE_ID = 0x0080;
    private const int FLAG_TIMESTAMP = 0x0040;
    private const int FLAG_TYPE = 0x0020;
    private const int FLAG_USER_ID = 0x0010;
    private const int FLAG_APP_ID = 0x0008;
    private const int FLAG_RESERVED1 = 0x0004;

    /**
     * @param non-negative-int $bodyLen
     * @param array<string, mixed> $headers
     * @param ?int<0, 9> $priority
     * @param ?non-empty-string $correlationId
     */
    private function __construct(
        public int $bodyLen = 0,
        public array $headers = [],
        public ?string $contentType = null,
        public ?string $contentEncoding = null,
        public DeliveryMode $deliveryMode = DeliveryMode::Whatever,
        public ?int $priority = null,
        public ?string $correlationId = null,
        public ?string $replyTo = null,
        public ?TimeSpan $expiration = null,
        public ?string $messageId = null,
        public ?\DateTimeImmutable $timestamp = null,
        public ?string $type = null,
        public ?string $userId = null,
        public ?string $appId = null,
    ) {}

    public static function fromMessage(Message $message): self
    {
        return new self(
            bodyLen: \strlen($message->body),
            headers: $message->headers,
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
    }

    /**
     * @return non-negative-int
     */
    public function mask(): int
    {
        $mask = 0;

        if ($this->contentType !== null && $this->contentType !== '') {
            $mask |= self::FLAG_CONTENT_TYPE;
        }

        if ($this->contentEncoding !== null && $this->contentEncoding !== '') {
            $mask |= self::FLAG_CONTENT_ENCODING;
        }

        if (\count($this->headers) > 0) {
            $mask |= self::FLAG_HEADERS;
        }

        if ($this->deliveryMode !== DeliveryMode::Whatever) {
            $mask |= self::FLAG_DELIVERY_MODE;
        }

        if ($this->priority !== null) {
            $mask |= self::FLAG_PRIORITY;
        }

        if ($this->correlationId !== null) {
            $mask |= self::FLAG_CORRELATION_ID;
        }

        if ($this->replyTo !== null && $this->replyTo !== '') {
            $mask |= self::FLAG_REPLY_TO;
        }

        if ($this->expiration !== null) {
            $mask |= self::FLAG_EXPIRATION;
        }

        if ($this->messageId !== null && $this->messageId !== '') {
            $mask |= self::FLAG_MESSAGE_ID;
        }

        if ($this->timestamp?->getTimestamp() > 0) {
            $mask |= self::FLAG_TIMESTAMP;
        }

        if ($this->type !== null && $this->type !== '') {
            $mask |= self::FLAG_TYPE;
        }

        if ($this->userId !== null && $this->userId !== '') {
            $mask |= self::FLAG_USER_ID;
        }

        if ($this->appId !== null && $this->appId !== '') {
            $mask |= self::FLAG_APP_ID;
        }

        /** @var non-negative-int */
        return $mask;
    }

    /**
     * @return non-negative-int
     */
    public function size(): int
    {
        $size = 0;

        if ($this->contentType !== null && $this->contentType !== '') {
            $size += 1 + \strlen($this->contentType);
        }

        if ($this->contentEncoding !== null && $this->contentEncoding !== '') {
            $size += 1 + \strlen($this->contentEncoding);
        }

        if (\count($this->headers) > 0) {
            $buffer = Io\Buffer::empty();
            $buffer->writeTable($this->headers);

            $size += \count($buffer);
        }

        if ($this->deliveryMode !== DeliveryMode::Whatever) {
            ++$size;
        }

        if ($this->priority !== null) {
            ++$size;
        }

        if ($this->correlationId !== null) {
            $size += 1 + \strlen($this->correlationId);
        }

        if ($this->replyTo !== null && $this->replyTo !== '') {
            $size += 1 + \strlen($this->replyTo);
        }

        if ($this->expiration !== null) {
            $size += 1 + \strlen((string) $this->expiration->toMilliseconds());
        }

        if ($this->messageId !== null && $this->messageId !== '') {
            $size += 1 + \strlen($this->messageId);
        }

        if ($this->timestamp?->getTimestamp() > 0) {
            $size += 8;
        }

        if ($this->type !== null && $this->type !== '') {
            $size += 1 + \strlen($this->type);
        }

        if ($this->userId !== null && $this->userId !== '') {
            $size += 1 + \strlen($this->userId);
        }

        if ($this->appId !== null && $this->appId !== '') {
            $size += 1 + \strlen($this->appId);
        }

        /** @var non-negative-int */
        return $size;
    }

    /**
     * @param non-negative-int $mask
     */
    public function write(Io\WriteBytes $writer, int $mask): Io\WriteBytes
    {
        if (self::hasSet($mask, self::FLAG_CONTENT_TYPE) && $this->contentType !== null) {
            $writer->writeString($this->contentType);
        }

        if (self::hasSet($mask, self::FLAG_CONTENT_ENCODING) && $this->contentEncoding !== null) {
            $writer->writeString($this->contentEncoding);
        }

        if (self::hasSet($mask, self::FLAG_HEADERS) && \count($this->headers) > 0) {
            $writer->writeTable($this->headers);
        }

        if (self::hasSet($mask, self::FLAG_DELIVERY_MODE) && $this->deliveryMode !== DeliveryMode::Whatever) {
            $writer->writeUint8($this->deliveryMode->value);
        }

        if (self::hasSet($mask, self::FLAG_PRIORITY) && $this->priority !== null) {
            $writer->writeUint8($this->priority);
        }

        if (self::hasSet($mask, self::FLAG_CORRELATION_ID) && $this->correlationId !== null) {
            $writer->writeString($this->correlationId);
        }

        if (self::hasSet($mask, self::FLAG_REPLY_TO) && $this->replyTo !== null) {
            $writer->writeString($this->replyTo);
        }

        if (self::hasSet($mask, self::FLAG_EXPIRATION) && $this->expiration !== null) {
            $writer->writeString((string) $this->expiration->toMilliseconds());
        }

        if (self::hasSet($mask, self::FLAG_MESSAGE_ID) && $this->messageId !== null) {
            $writer->writeString($this->messageId);
        }

        if (self::hasSet($mask, self::FLAG_TIMESTAMP) && $this->timestamp !== null && $this->timestamp->getTimestamp() > 0) {
            $writer->writeTimestamp($this->timestamp);
        }

        if (self::hasSet($mask, self::FLAG_TYPE) && $this->type !== null) {
            $writer->writeString($this->type);
        }

        if (self::hasSet($mask, self::FLAG_USER_ID) && $this->userId !== null) {
            $writer->writeString($this->userId);
        }

        if (self::hasSet($mask, self::FLAG_APP_ID) && $this->appId !== null) {
            $writer->writeString($this->appId);
        }

        return $writer;
    }

    /**
     * @param non-negative-int $mask
     */
    public static function read(Io\ReadBytes $reader, int $mask): self
    {
        $contentType = self::hasSet($mask, self::FLAG_CONTENT_TYPE) ? $reader->readString() : null;
        $contentEncoding = self::hasSet($mask, self::FLAG_CONTENT_ENCODING) ? $reader->readString() : null;
        $headers = self::hasSet($mask, self::FLAG_HEADERS) ? $reader->readTable() : [];
        $deliveryMode = self::hasSet($mask, self::FLAG_DELIVERY_MODE) ? (DeliveryMode::tryFrom($reader->readUint8()) ?: DeliveryMode::Whatever) : DeliveryMode::Whatever;
        /** @var ?int<0, 9> $priority */
        $priority = self::hasSet($mask, self::FLAG_PRIORITY) ? $reader->readUint8() : null;
        /** @var non-empty-string $correlationId */
        $correlationId = self::hasSet($mask, self::FLAG_CORRELATION_ID) ? $reader->readString() : null;
        $replyTo = self::hasSet($mask, self::FLAG_REPLY_TO) ? $reader->readString() : null;
        $expiration = self::hasSet($mask, self::FLAG_EXPIRATION) ? TimeSpan::fromMilliseconds((int) $reader->readString()) : null;
        $messageId = self::hasSet($mask, self::FLAG_MESSAGE_ID) ? $reader->readString() : null;
        $timestamp = self::hasSet($mask, self::FLAG_TIMESTAMP) ? $reader->readTimestamp() : null;
        $type = self::hasSet($mask, self::FLAG_TYPE) ? $reader->readString() : null;
        $userId = self::hasSet($mask, self::FLAG_USER_ID) ? $reader->readString() : null;
        $appId = self::hasSet($mask, self::FLAG_APP_ID) ? $reader->readString() : null;

        if (self::hasSet($mask, self::FLAG_RESERVED1)) {
            $reader->readString();
        }

        return new self(
            headers: $headers,
            contentType: $contentType,
            contentEncoding: $contentEncoding,
            deliveryMode: $deliveryMode,
            priority: $priority,
            correlationId: $correlationId,
            replyTo: $replyTo,
            expiration: $expiration,
            messageId: $messageId,
            timestamp: $timestamp,
            type: $type,
            userId: $userId,
            appId: $appId,
        );
    }

    /**
     * @param non-negative-int $mask
     * @param self::* $property
     */
    private static function hasSet(int $mask, int $property): bool
    {
        return ($mask & $property) > 0;
    }
}
