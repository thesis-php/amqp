<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Revolt\EventLoop;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;

$client = new Client(Config::default());

$channel = $client->channel();

$queue = $channel->queueDeclare(autoDelete: true);

$messageId = 0;

EventLoop::unreference(
    EventLoop::repeat(0.5, static function () use ($queue, $channel, &$messageId): void {
        ++$messageId;

        $channel->publish(new Message("Message#{$messageId}"), routingKey: $queue->name);
    }),
);

$channel->qos(prefetchCount: 1);
$deliveries = $channel->consumeIterator($queue->name, size: 1);

Amp\async(static function () use ($deliveries): void {
    Amp\trapSignal([\SIGINT, \SIGTERM]);
    $deliveries->complete();
});

foreach ($deliveries as $delivery) {
    dump($delivery->message->body);
    $delivery->ack();
}

dump('Consumer cancelled by signal.');

$client->disconnect();
