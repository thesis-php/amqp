<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PublishBatchConfirmationResult::class)]
final class PublishBatchConfirmationResultTest extends TestCase
{
    public function testOk(): void
    {
        $result = new PublishBatchConfirmationResult(
            unconfirmed: [
                new PublishMessage(new Message()),
            ],
            unrouted: [
                new PublishMessage(new Message()),
            ],
        );

        $this->expectExceptionObject(new \RuntimeException('Failed to publish 2 messages.'));

        $result->ok();
    }
}
