<?php

declare(strict_types=1);

use Amp\TimeoutCancellation;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;
use Thesis\Amqp\Rpc;
use function Amp\trapSignal;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::default());
$channel = $client->channel();
$queue = $channel->queueDeclare(autoDelete: true);

$channel->consume(
    callback: static function (DeliveryMessage $delivery): void {
        $delivery->reply(new Message("Request '{$delivery->message->body}' handled."));
    },
    queue: $queue->name,
    noAck: true,
);

$rpc = new Rpc($client);

for ($i = 0; $i < 100; ++$i) {
    dump($rpc->request(new Message("Request#{$i}"), routingKey: $queue->name, cancellation: new TimeoutCancellation(2))->body);
}

trapSignal([\SIGINT, \SIGTERM]);

$client->disconnect();
