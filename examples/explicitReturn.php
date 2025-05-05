<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishResult;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673'));
$channel = $client->channel();

$channel->queueDelete('xxx');

$channel->confirmSelect();

$msg = new Message('abz');

$confirmation = $channel->publish($msg, routingKey: 'xxx', mandatory: true);
$result = $confirmation?->await();

if ($result === PublishResult::Unrouted) {
    dump('Message cannot be routed. Creating queue explicitly...');

    $channel->queueDeclare('xxx', durable: true);
}

$channel->publish($msg, routingKey: 'xxx', mandatory: true)?->await();

$msg = $channel->get('xxx');
dump("Now message '{$msg?->message->body}' was published.");

$client->disconnect();
