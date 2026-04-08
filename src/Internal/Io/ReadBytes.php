<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Io;

use BcMath\Number;
use Thesis\Endian\Order;

/**
 * @internal
 *
 * @phpstan-import-type Int8 from Order
 * @phpstan-import-type Uint8 from Order
 * @phpstan-import-type Int16 from Order
 * @phpstan-import-type Uint16 from Order
 * @phpstan-import-type Int32 from Order
 * @phpstan-import-type Uint32 from Order
 */
interface ReadBytes
{
    /**
     * @return Int8
     */
    public function readInt8(): int;

    /**
     * @return Uint8
     */
    public function readUint8(): int;

    /**
     * @return Int16
     */
    public function readInt16(): int;

    /**
     * @return Uint16
     */
    public function readUint16(): int;

    /**
     * @return Int32
     */
    public function readInt32(): int;

    /**
     * @return Uint32
     */
    public function readUint32(): int;

    public function readInt64(): Number;

    public function readUint64(): Number;

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
