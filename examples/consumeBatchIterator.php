<?php

declare(strict_types=1);

use Amp\Future;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishMessage;
use function Amp\async;
use function Amp\trapSignal;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673'));
$channel = $client->channel();

$queue = $channel->queueDeclare(autoDelete: true);

$channel->confirmSelect();

$channel
    ->publishBatch(array_map(
        static fn(int $number): PublishMessage => new PublishMessage(new Message("{$number}"), routingKey: $queue->name),
        range(1, 8),
    ))
    ->awaitAll();

$iterator = $channel->consumeBatchIterator(count: 5, timeout: 1, queue: $queue->name);

/** @var Future<int> $future */
$future = async(static function () use ($iterator): int {
    $signal = trapSignal([\SIGINT, \SIGTERM]);
    $iterator->complete();

    return $signal;
});

foreach ($iterator as $batch) {
    dump(array_map(
        static fn(DeliveryMessage $delivery): string => $delivery->message->body,
        $batch->deliveries,
    ));

    $batch->ack();
}

dump("signal received: {$future->await()}");

$channel->close();
