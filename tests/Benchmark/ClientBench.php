<?php

declare(strict_types=1);

namespace Thesis\Amqp\Benchmark;

use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use Thesis\Amqp\Client;
use Thesis\Amqp\Config;

#[BeforeMethods('setUp')]
#[AfterMethods('tearDown')]
final class ClientBench
{
    private Client $client;

    public function setUp(): void
    {
        $this->client = new Client(Config::default());
    }

    public function tearDown(): void
    {
        $this->client->disconnect();
    }

    #[Revs(1)]
    public function benchChannel(): void
    {
        for ($i = 0; $i < 8188; ++$i) {
            $this->client->channel();
        }
    }
}
