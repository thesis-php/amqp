<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\ConsumeBatch;
use Thesis\Amqp\ConsumeBatchOptions;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishMessage;
use function Amp\trapSignal;

require_once __DIR__.'/../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673'));
$channel = $client->channel();

$channel->queueDeclare('test', durable: true);

$channel->confirmSelect();

$channel
    ->publishBatch(array_map(
        static function (int $number): PublishMessage {
            return new PublishMessage(new Message("{$number}"), routingKey: 'test');
        },
        \range(1, 7),
    ))
    ->awaitAll()
;

$canceller = $channel
    ->batchConsumer(new ConsumeBatchOptions(5, timeout: 1))
    ->consume(
        function (ConsumeBatch $batch): void {
            dump(array_map(static fn (DeliveryMessage $delivery): string => $delivery->message->body, $batch->deliveries));
        },
        queue: 'test',
    )
;

trapSignal([\SIGINT, \SIGTERM]);

$canceller->complete();
