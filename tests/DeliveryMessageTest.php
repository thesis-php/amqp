<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use function Amp\async;
use function Amp\Future\awaitAll;

#[CoversClass(DeliveryMessage::class)]
final class DeliveryMessageTest extends TestCase
{
    /**
     * @param 'ack'|'nack'|'reject'|'reply' $operationType
     */
    #[TestWith(['ack'])]
    #[TestWith(['nack'])]
    #[TestWith(['reject'])]
    #[TestWith(['reply'])]
    public function testRepetitiveOperation(string $operationType): void
    {
        $count = 0;
        $operation = static function () use (&$count): void {
            ++$count;
        };

        $delivery = new DeliveryMessage(
            ack: $operation,
            nack: $operation,
            reject: $operation,
            reply: $operation,
            message: new Message('x'),
        );

        $call = match ($operationType) {
            'ack' => $delivery->ack(...),
            'nack' => $delivery->nack(...),
            'reject' => $delivery->reject(...),
            'reply' => static fn() => $delivery->reply(new Message()),
        };

        $futures = [];

        for ($i = 0; $i < 10; ++$i) {
            $futures[] = async($call);
        }

        awaitAll($futures);

        self::assertSame(1, $count);
    }
}
