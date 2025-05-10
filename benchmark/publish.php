<?php

declare(strict_types=1);

use DragonCode\Benchmark\Benchmark;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishMessage;

require __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();
$confirmChannel = $client->channel();
$confirmChannel->confirmSelect();

$channel->exchangeDeclare('thesis_bench_exchange');
$channel->queueDeclare('thesis_bench_queue');
$channel->queueBind('thesis_bench_queue', 'thesis_bench_exchange');

$message = new Message(bin2hex(random_bytes(1024)));

$iterations = isset($argv[1]) ? (int) $argv[1] : 100_000;
$batch = $iterations / 100;

Benchmark::start()
    ->withoutData()
    ->iterations(1)
    ->compare([
        'publish' => static function () use ($channel, $message, $iterations): void {
            for ($i = 0; $i < $iterations; ++$i) {
                $channel->publish($message, exchange: 'thesis_bench_exchange');
            }

            $channel->publish(new Message('quit'), exchange: 'thesis_bench_exchange');
        },
        'publishConfirm' => static function () use ($confirmChannel, $message, $iterations): void {
            for ($i = 0; $i < $iterations; ++$i) {
                $confirmChannel->publish($message, exchange: 'thesis_bench_exchange')?->await();
            }

            $confirmChannel->publish(new Message('quit'), exchange: 'thesis_bench_exchange')?->await();
        },
        'publishBatch' => static function () use ($channel, $message, $iterations): void {
            $batch = 100;
            $iterations /= $batch;

            for ($i = 0; $i < $iterations; ++$i) {
                $messages = [];

                for ($j = 0; $j < 100; ++$j) {
                    $messages[] = new PublishMessage($message, exchange: 'thesis_bench_exchange');
                }

                $channel->publishBatch($messages);
            }

            $channel->publish(new Message('quit'), exchange: 'thesis_bench_exchange');
        },
    ]);

$client->disconnect();
