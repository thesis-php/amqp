# Thesis Amqp

Pure asynchronous (fiber based) strictly typed full-featured PHP driver for AMQP 0.9.1 protocol.

## Contents
- [Installation](#installation)
- [Configuration](#configuration)
  - [vhost](#vhost) 
  - [auth_mechanism](#auth_mechanism)
  - [heartbeat](#heartbeat)
  - [connection_timeout](#connection_timeout)
  - [channel_max](#channel_max)
  - [frame_max](#frame_max)
  - [tcp_nodelay](#tcp_nodelay)
- [Client](#client)
- [Channel](#channel)
  - [exchange declare](#exchange-declare)
  - [exchange bind](#exchange-bind)
  - [exchange unbind](#exchange-unbind)
  - [exchange delete](#exchange-delete)
  - [queue declare](#queue-declare)
  - [queue bind](#queue-bind)
  - [queue unbind](#queue-unbind)
  - [queue purge](#queue-purge)
  - [queue delete](#queue-delete)
  - [publish](#publish)
  - [publish batch](#publish-batch)
  - [get](#get)
  - [ack](#ack)
  - [nack](#nack)
  - [reject](#reject)
  - [ack, nack, reject safety](#ack-nack-reject-safety)
  - [consume](#consume)
  - [consume iterator](#consume-iterator)
  - [consume batch](#consume-batch)
  - [consume batch iterator](#consume-batch-iterator)
  - [tx](#tx)
  - [transactional](#transactional)
  - [confirms](#confirms)
  - [returns](#returns)
  - [explicit returns](#explicit-returns)
- [License](#license)

### Installation

```shell
composer require thesis/amqp
```

### Configuration

Configuration can be created from dsn, that follows the [amqp uri spec](https://www.rabbitmq.com/docs/uri-spec).

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/');
```

Multiple addresses are supported. The client will connect to the first available amqp server host.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672,localhost:5673/');
```

From array (for example, if you keep the configuration of your application as an array).

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromArray([
    'scheme' => 'amqp',
    'urls' => ['localhost:5672'],
    'user' => 'guest',
    'password' => 'guest',
]);
```

From primary constructor.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = new Config(
    urls: ['localhost:5672'],
    user: 'guest',
    vhost: '/test',
    authMechanisms: ['plain', 'amqplain'],
);
```

If the original amqp server settings remain unchanged, you can use `Config::default()`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::default(); // amqp://guest:guest@localhost:5672/
```

#### vhost

The `vhost` value should be configured with the path parameter.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/test');
```

#### auth_mechanism

To configure priority and availability of auth mechanisms provide query parameter `auth_mechanism`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?auth_mechanism=amqplain&auth_mechanism=plain');
```

By default `plain` will be used. Current supported authentication mechanisms are `plain` and `amqplain`.

#### heartbeat

The heartbeat value must be in seconds.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?heartbeat=30');
```

By default `60 seconds` will be used as RabbitMQ [suggest](https://www.rabbitmq.com/docs/heartbeats#heartbeats-timeout). To disable heartbeats set `0`. 

#### connection_timeout

To configure tcp connection timeout use `connection_timeout` with value in seconds.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?connection_timeout=10');
```

The default value is `1000 milliseconds`.

#### channel_max

The `channel_max` value tells to the client and amqp server how many channels will be used. The maximum and default is `65535`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?channel_max=30000');
```

When the channel limit is exhausted, you will get an `Thesis\Amqp\Exception\NoAvailableChannel` exception.

#### frame_max

`frame_max` sets a size of chunks. By default, this setting uses `65535 bytes` (and this is the maximum).
If you doesn't understand the setting, you shouldn't change this value.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?frame_max=50000');
```

#### tcp_nodelay

You can disable `tcp nodelay` by setting the value to `false`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;

$config = Config::fromURI('amqp://guest:guest@localhost:5672/?tcp_nodelay=false');
```

Since we use an internal buffer to work with data, which reduces the number of network accesses, there is no need to turn off `tcp nodelay`.
This may **seriously reduce** client performance.

### Client

The client is the connection facade to the `amqp` server. It is responsible for connecting and disconnecting (also closing all channels) from the server.
It is not necessary to explicitly connect to work with the client. The connection will be established when the first channel is created.
```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

// your code here

$client->disconnect();
```

### Channel

The new channel can be obtained **only** from the client.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->close();

$client->disconnect();
```

- If you are terminating an application, you don't have to call `$channel->close()`, because `$client->disconnect()` will close all channels anyway.
- However, you cannot leave channels open during the life of the application without using them – otherwise you may exhaust the open channel limit from the `channel_max` setting.
- After closing a channel yourself or getting a `Thesis\Amqp\Exception\ChannelWasClosed` exception, you cannot use the channel – open a new one.

#### exchange declare

`exchangeDeclare` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->exchangeDeclare('events', durable: true);
```

#### exchange bind

`exchangeBind` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->exchangeBind('service.a', 'service.b');
```

#### exchange unbind

`exchangeUnbind` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->exchangeUnbind('service.a', 'service.b');
```

#### exchange delete

`exchangeDelete` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->exchangeDelete('service.a', ifUnused: true);
```

#### queue declare

`queueDeclare` returns a `Queue` object if `noWait` is set to `false`. Otherwise, `null` is returned, and this is checked statically.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$queue = $channel->queueDeclare('service.a.events');

var_dump($queue->messages, $queue->consumers);
```

#### queue bind

`queueBind` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->queueBind('service.a.events', 'service.a');
```

#### queue unbind

`queueUnbind` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->queueUnbind('service.a.events', 'service.a');
```

#### queue purge

`queuePurge` returns a purged message count if `noWait` is set to `false`. Otherwise, `null` is returned, and this is checked statically.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$messages = $channel->queuePurge('service.a.events');
var_dump($messages);
```

#### queue delete

`queueDelete` returns a deleted message count if `noWait` is set to `false`. Otherwise, `null` is returned, and this is checked statically.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$messages = $channel->queueDelete('service.a.events', ifUnused: true, ifEmpty: true);
var_dump($messages);
```

#### publish

There are notable changes here compared to other libraries.
- First, the message is an object.
- Secondly, all system headers like `correlationId`, `expiration`, `messageId` and so on are placed in the properties of this object, so you don't have to pass them through user headers and remember how keys should be named.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Thesis\Amqp\Message;
use Thesis\Amqp\DeliveryMode;
use Thesis\Time\TimeSpan;

$client = new Client(Config::default());

$channel = $client->channel();
$channel->publish(new Message(
    body: '...',
    headers: ['x' => 'y'],
    contentType: 'application/json',
    contentEncoding: 'json',
    deliveryMode: DeliveryMode::Persistent,
    expiration: TimeSpan::fromSeconds(5),
));
```

#### publish batch

You can publish a batch of messages.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishMessage;

$client = new Client(Config::default());

$channel = $client->channel();
$confirmation = $channel->publishBatch([
    new PublishMessage(new Message('x'), routingKey: 'test'),
    new PublishMessage(new Message('y'), routingKey: 'test'),
    new PublishMessage(new Message('z'), routingKey: 'test'),
]);

// Only if $channel->confirmSelect() was called.
$unconfirmed = $confirmation->unconfirmed();

if (\count($unconfirmed) > 0) {
    // retry publish.
}
```

You will receive back the `PublishBatchConfirmation`, which allows you to deal with confirmations:
- `PublishBatchConfirmation::awaitAll` - await all confirmations or throw an `\LogicException`, if there are any unconfirmed messages.
- `PublishBatchConfirmation::unconfirmed` - await all confirmations and return only unconfirmed ones.

#### get

`get` returns a `Delivery` object, which also has all system headers placed in properties.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events', noAck: true);

var_dump($delivery?->message);
```

#### ack

`ack` can be called on a `Delivery` object.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
$delivery?->ack();
```

Or through a channel.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
if ($delivery !== null) {
    $channel->ack($delivery);
}
```

It is **safe** to call `ack` many times.

#### nack

`nack` can be called on a `Delivery` object.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
$delivery?->nack(requeue: false);
```

Or through a channel.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
if ($delivery !== null) {
    $channel->nack($delivery, requeue: false);
}
```

It is **safe** to call `nack` many times.

#### reject

`reject` can be called on a `Delivery` object.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
$delivery?->reject(requeue: false);
```

Or through a channel.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;

$client = new Client(Config::default());

$channel = $client->channel();
$delivery = $channel->get('service.a.events');
if ($delivery !== null) {
    $channel->reject($delivery, requeue: false);
}
```

It is **safe** to call `reject` many times.

#### ack, nack, reject safety

It is safe to call `nack/reject` after `ack` or competitively. Operations will be ordered and processed only once.
For example, you want to call `nack` on any error and `ack` only on successful cases. Then you can write the code as follows:

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Channel;

$client = new Client(Config::default());

$channel = $client->channel();

$handler = function (DeliveryMessage $delivery) use ($httpclient): void {
    // handle the delivery with an \Exception
    $delivery->nack();
};

$delivery = $channel->get('test');
\assert($delivery !== null);

try {
    $handler($delivery);
} finally {
    $delivery->ack();
}
```

Here `ack` in `finally` block will only be sent if neither `nack`, `reject`, nor `ack` in the `$handler` is called.

#### consume

`consume` accepts a callback where `Delivery` and `Channel` will be passed to.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Channel;

$client = new Client(Config::default());

$channel = $client->channel();

$channel->qos(prefetchCount: 1);
$consumerTag = $channel->consume(static function (DeliveryMessage $delivery, Channel $_): void {
    var_dump($delivery->message);
    $delivery->ack();    
}, queue: 'service.a.events');

$channel->cancel($consumerTag);
```

#### consume iterator

If you don't like the `callback api` like I do, you can handle messages through an `iterator`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Amp;

$client = new Client(Config::default());

$channel = $client->channel();

$channel->qos(prefetchCount: 1);
$deliveries = $channel->consumeIterator('service.a.events', size: 1);

Amp\async(static function () use ($deliveries): void {
    Amp\trapSignal([\SIGINT, \SIGTERM]);
    $deliveries->complete();
});

foreach ($deliveries as $delivery) {
    var_dump($delivery->message);
    $delivery->ack();
}

$client->disconnect();
```

- The size of the `Iterator` should be equal to the `prefetch count` provided to `Channel::qos()`.
- The `Iterator::complete()` will cancel the consumer and stop the loop.

Also, you can throw an exception using `Iterator::cancel`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Config;
use Thesis\Amqp\Client;
use Amp;

$client = new Client(Config::default());

$channel = $client->channel();

$channel->qos(prefetchCount: 1);
$deliveries = $channel->consumeIterator('service.a.events', size: 1);

Amp\async(static function () use ($deliveries): void {
    Amp\trapSignal([\SIGINT, \SIGTERM]);
    $deliveries->cancel(new \Exception('you should stop'));
});

try {
    foreach ($deliveries as $delivery) {
        var_dump($delivery->message);
        $delivery->ack();
    }
} catch (\Throwable $e) {
    var_dump($e->getMessage()); // you should stop
}

$client->disconnect();
```

#### consume batch

Although AMQP doesn't have a native way to receive messages in batches, we can achieve this using two operations — `basic.qos(count: N)` and `basic.ack(multiple: true)` on the last message. `basic.qos` limits the number of messages the AMQP server can push to our consumer, and this number should match the batch size.
`basic.ack(multiple: true)` allows us to send a single acknowledgment for the entire batch. You don’t need to implement this yourself — it's included with this library.
Simply use `Channel::consumeBatch` and pass a callback. As an argument, you’ll receive a `ConsumeBatch` instance, on which you can call `ack` or `nack`.
Note that you don’t need to call these functions on individual `DeliveryMessage` — only on the `ConsumeBatch`!

However, since it may take a while to fill a batch, you can specify a `timeout`. This way, you'll receive a non-empty batch either when the required number of messages is collected or when the timer expires — whichever comes first.
See the [example](examples/consumeBatch.php): you'll see two batches there — one will arrive immediately because the queue already contains enough messages and the second will arrive after a 1-second wait, consisting of just 3 messages.

Since `basic.qos(count: N)` is a crucial requirement for implementing batching, the `consumeBatch` and `consumeBatchIterator` methods call it automatically.
**You don’t need to call `Channel::qos` yourself!**

#### consume batch iterator

Just like with regular `consumeIterator`, where you can work with an `Iterator`, you can also process batches using an `Iterator`.
By using `consumeBatchIterator` instead of `consumeBatch`, you get an `Iterator` where each element is a `ConsumeBatch`. See the [example](examples/consumeBatchIterator.php) to understand how to use it.

#### tx

`transactions` follows the standard amqp client api. No notable changes here.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Message;
use Thesis\Amqp\Config;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();

$channel->txSelect();

$channel->publish(new Message('...'), routingKey: 'test');
$channel->publish(new Message('...'), routingKey: 'test');
$channel->txCommit();

$channel->publish(new Message('...'), routingKey: 'test');
$channel->publish(new Message('...'), routingKey: 'test');
$channel->txRollback();

$client->disconnect();
```

- you can't call `txSelect` more than once.
- after switching to the [confirmation mode](#confirms), transactions will be unavailable.

#### transactional

If you prefer not to manage the transaction yourself, you can use the `Channel::transactional` method, which will put the channel into transactional mode and commit or rollback the transaction in case of an exception.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Channel;
use Thesis\Amqp\Client;
use Thesis\Amqp\Message;
use Thesis\Amqp\Config;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();

$channel->transactional(static function (Channel $channel): void {
    $channel->publish(new Message('...'), routingKey: 'test');
    $channel->publish(new Message('...'), routingKey: 'test');
    $channel->publish(new Message('...'), routingKey: 'test');
});

try {
    $channel->transactional(static function (Channel $channel): void {
        $channel->publish(new Message('...'), routingKey: 'test');
        $channel->publish(new Message('...'), routingKey: 'test');
        throw new \DomainException('Ops.');
    });
} catch (\Throwable $e) {
    var_dump($e->getMessage()); // Ops.
}

$client->disconnect();
```

#### confirms

There are notable changes here compared to other libraries. Instead of a callback api through which you could handle confirmations,
you get a `PublishConfirmation` object that can be waited on in non-blocking mode via `await`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Message;
use Thesis\Amqp\Config;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();
$channel->confirmSelect();

$confirmation = $channel->publish(new Message('...'), routingKey: 'test');
var_dump($confirmation?->await());

$client->disconnect();
```

The `PublishConfirmation::await` will return `PublishResult` enum that can be in one of the `Acked, Nacked, Canceled, Waiting` states.

Since confirmations can return in batches, there is no need to wait for each confirmation in turn. Instead, you can publish many messages and wait for a confirmation at the end.
If you are lucky, the amqp server will return multiple confirmations, or even one for the entire batches.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\PublishConfirmation;
use Thesis\Amqp\Message;
use Thesis\Amqp\Config;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673/'));

$channel = $client->channel();
$channel->confirmSelect();

$confirmations = [];
for ($i = 0; $i < 100; ++$i) {
    $confirmation = $channel->publish(new Message('...'), routingKey: 'test');
    \assert($confirmation !== null);

    $confirmations[] = $confirmation;
}

PublishConfirmation::awaitAll($confirmations);

$client->disconnect();
```

#### returns

Returned messages (with `mandatory` flag set on `Channel::publish`) can be handled in a separate callback.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;
use Thesis\Amqp\DeliveryMessage;
use Amp;

require_once __DIR__ . '/../../vendor/autoload.php';

$client = new Client(Config::default());

$channel = $client->channel();
$channel->onReturn(static function (DeliveryMessage $delivery): void {
    // handle returns here
});

$channel->publish(new Message('...'), routingKey: 'not_exists', mandatory: true);

$client->disconnect();
```

#### explicit returns

In AMQP messaging system it’s possible for a published message to have no destination. This is acceptable in some scenarios such as the [Publish-Subscribe](https://www.enterpriseintegrationpatterns.com/patterns/messaging/PublishSubscribeChannel.html) pattern, where it’s fine for events to go unhandled, but not in others.
For example, in the [Command](https://www.enterpriseintegrationpatterns.com/patterns/messaging/CommandMessage.html) pattern every message is expected to be processed.

To detect and react to such delivery failures, you must publish messages with the `mandatory` flag enabled. This tells the AMQP server to return any message that cannot be routed to at least one queue.

However, there’s a challenge: returned messages are delivered asynchronously via a separate *thread* (not the OS thread) and are not associated with the original publishing request.
This means the publisher has no immediate way of knowing whether a message was routed or returned. In some cases, you may want to know this synchronously, so that you can:
- Log the message;
- Store the message in the DB;
- Automatically declare the required topology (e.g., queues or bindings) and republish.

To support this use case, the library provides a mechanism based on `publisher confirms` and a custom header:
- Enable `publisher confirm` mode;
- Set the `mandatory` flag when publishing.

The library will add a special header `X-Thesis-Mandatory-Id`. This allows the library to correlate any returned message with its original publish request.
If the message is unroutable, the library will return `PublishResult::Unrouted`.

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\Message;
use Thesis\Amqp\PublishResult;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::fromURI('amqp://thesis:secret@localhost:5673'));
$channel = $client->channel();

$channel->confirmSelect();

$confirmation = $channel->publish(new Message('abz'), routingKey: 'xxx', mandatory: true);

if ($confirmation?->await() === PublishResult::Unrouted) {
    // handle use case
}
```

> ⚠️ Important: This mechanism only works if `publisher confirms` are enabled. Without them the library cannot track which messages were successfully published to queues, because no frame will receive.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
