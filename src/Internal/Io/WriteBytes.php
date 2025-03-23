<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

use Thesis\ByteWriter\Writer;

/**
 * @internal
 */
interface WriteBytes
{
    /**
     * @param non-negative-int $v
     */
    public function writeUint8(int $v): self;

    public function writeInt16(int $v): self;

    /**
     * @param non-negative-int $v
     */
    public function writeUint16(int $v): self;

    public function writeInt32(int $v): self;

    /**
     * @param non-negative-int $v
     */
    public function writeUint32(int $v): self;

    /**
     * @param non-negative-int $v
     */
    public function writeUint64(int $v): self;

    public function writeDouble(float $v): self;

    public function writeString(string $v): self;

    public function writeText(string $v): self;

    public function writeTimestamp(\DateTimeImmutable $date): self;

    /**
     * @param array<array-key, mixed> $values
     */
    public function writeTable(array $values): self;

    /**
     * @param list<mixed> $values
     */
    public function writeArray(array $values): self;

    public function writeValue(mixed $value): self;

    public function writeBits(bool ...$bits): self;

    public function write(string $v): self;

    /**
     * @param callable(non-negative-int): non-empty-string $reserve
     * @param callable(self): void $write
     */
    public function reserve(callable $reserve, callable $write): self;

    /**
     * @throws \Throwable
     */
    public function writeTo(Writer $writer): void;
}
