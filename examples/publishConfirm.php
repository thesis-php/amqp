<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishMessage;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::default());
$channel = $client->channel();

$queue = $channel->queueDeclare(autoDelete: true);

$channel->confirmSelect();

$channel->publish(new Message('xxx'))?->await();

$channel
    ->publishBatch(array_map(
        static fn(int $number): PublishMessage => new PublishMessage(new Message("{$number}"), routingKey: $queue->name),
        range(1, 8),
    ))
    ->await()
    ->ensureAllPublished();

dump('Messages published successfully.');
