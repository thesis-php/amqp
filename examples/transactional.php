<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Thesis\Amqp\Channel;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;

$client = new Client(Config::default());

$channel = $client->channel();

$queue = $channel->queueDeclare(autoDelete: true);

$channel->transactional(static function (Channel $channel) use ($queue): void {
    $channel->publish(new Message('1'), routingKey: $queue->name);
    $channel->publish(new Message('2'), routingKey: $queue->name);
    $channel->publish(new Message('3'), routingKey: $queue->name);
});

try {
    $channel->transactional(static function (Channel $channel) use ($queue): void {
        $channel->publish(new Message('4'), routingKey: $queue->name);
        $channel->publish(new Message('5'), routingKey: $queue->name);

        throw new DomainException('Ops.');
    });
} catch (Throwable $e) {
    dump($e->getMessage()); // Ops.
}

dump('Count of messages in queue: ' . $channel->queueDelete($queue->name));

$client->disconnect();
