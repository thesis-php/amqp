<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;
use function Amp\trapSignal;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673'));
$channel = $client->channel();
$queue = $channel->queueDeclare(autoDelete: true);

$consumerTag = $channel->consume(
    callback: static function (DeliveryMessage $delivery): void {
        $delivery->reply(new Message("Request '{$delivery->message->body}' handled."));
    },
    queue: $queue->name,
    noAck: true,
);

$rpc = $client->rpc();

for ($i = 0; $i < 100; ++$i) {
    dump($rpc->request(new Message("Request#{$i}"), routingKey: $queue->name)->body);
}

trapSignal([\SIGINT, \SIGTERM]);

$client->disconnect();
