<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

/**
 * @internal
 */
interface ReadBytes
{
    public function readInt8(): int;

    /**
     * @return non-negative-int
     */
    public function readUint8(): int;

    public function readInt16(): int;

    /**
     * @return non-negative-int
     */
    public function readUint16(): int;

    public function readInt32(): int;

    /**
     * @return non-negative-int
     */
    public function readUint32(): int;

    public function readInt64(): int;

    /**
     * @return non-negative-int
     */
    public function readUint64(): int;

    public function readFloat(): float;

    public function readDouble(): float;

    /**
     * @throws \Throwable
     */
    public function readTimestamp(): \DateTimeImmutable;

    public function readDecimal(): int;

    public function readText(): string;

    public function readString(): string;

    /**
     * @return list<mixed>
     */
    public function readArray(): array;

    /**
     * @return array<string, mixed>
     */
    public function readTable(): array;

    /**
     * @param positive-int $n
     * @return non-empty-string
     */
    public function read(int $n): string;

    public function readValue(): mixed;

    /**
     * @param non-negative-int $n
     * @return non-empty-list<bool>
     */
    public function readBits(int $n): array;

    public function reset(): string;
}
