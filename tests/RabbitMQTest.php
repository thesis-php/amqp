<?php

declare(strict_types=1);

use Amp\DeferredFuture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Channel;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Event\ConnectionBlocked;
use Thesis\Amqp\Event\ConnectionUnblocked;
use Thesis\Amqp\EventDispatcher;
use Thesis\Amqp\Exception\ConnectionIsBlocked;
use Thesis\Amqp\Message;

#[CoversClass(Client::class)]
#[CoversClass(Channel::class)]
final class RabbitMQTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testConnectionBlockedUnblockedOnLowMemory(): void
    {
        self::markTestSkipped();

        /** @phpstan-ignore deadCode.unreachable */
        $deferredBlocked = new DeferredFuture();
        $deferredUnblocked = new DeferredFuture();

        $publishClient = new Client(
            config: new Config(urls: ['localhost:5673']),
            eventDispatcher: (new EventDispatcher())
                ->listen(ConnectionBlocked::class, static function () use ($deferredBlocked): void {
                    $deferredBlocked->complete();
                })
                ->listen(ConnectionUnblocked::class, static function () use ($deferredUnblocked): void {
                    $deferredUnblocked->complete();
                }),
        );
        $publishChannel = $publishClient->channel();

        $fixClient = new Client($publishClient->config);
        $fixChannel = $fixClient->channel();

        $queue = $publishChannel->queueDeclare(autoDelete: true);

        $message = new Message(body: str_repeat('x', 1024 * 10));

        try {
            while (true) {
                $publishChannel->publish($message, routingKey: $queue->name);
            }
        } catch (ConnectionIsBlocked) {
        }

        $deferredBlocked->getFuture()->await();

        $fixChannel->queueDelete($queue->name);

        $deferredUnblocked->getFuture()->await();
    }
}
