<?php

declare(strict_types=1);

use Amp\DeferredFuture;
use DragonCode\Benchmark\Benchmark;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\ConsumeBatch;
use Thesis\Amqp\DeliveryMessage;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();

Benchmark::start()
    ->withoutData()
    ->iterations(1)
    ->compare([
        'consume' => static function () use ($channel): void {
            $channel->qos(prefetchCount: 1);

            $deferred = new DeferredFuture();

            $consumerTag = $channel->consume(
                callback: static function (DeliveryMessage $delivery) use ($deferred): void {
                    if ($delivery->message->body === 'quit') {
                        $deferred->complete();
                    }
                },
                queue: 'thesis_bench_queue',
                noAck: true,
            );

            $deferred->getFuture()->await();
            $channel->cancel($consumerTag);
        },
        'consumeIterator' => static function () use ($channel): void {
            $channel->qos(prefetchCount: 1);

            $iterator = $channel->consumeIterator(queue: 'thesis_bench_queue', size: 1, noAck: true);

            foreach ($iterator as $delivery) {
                if ($delivery->message->body === 'quit') {
                    $iterator->complete();
                }
            }
        },
        'consumeBatch' => static function () use ($channel): void {
            $deferred = new DeferredFuture();

            $consumerTag = $channel->consumeBatch(
                callback: static function (ConsumeBatch $batch) use ($deferred): void {
                    foreach ($batch as $delivery) {
                        if ($delivery->message->body === 'quit') {
                            $deferred->complete();
                        }
                    }
                },
                count: 100,
                timeout: 0.100,
                queue: 'thesis_bench_queue',
                noAck: true,
            );

            $deferred->getFuture()->await();
            $channel->cancel($consumerTag);
        },
    ]);

$client->disconnect();
