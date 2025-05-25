# Thesis Amqp

Pure asynchronous (fiber based) strictly typed full-featured PHP driver for AMQP 0.9.1 protocol.

## Features

- Full support for AMQP 0.9.1
- [Supports AMQP uri specification](#configuration)
- [Publish messages in batch](examples/publishBatch.php)
- [Automatic acknowledges processing in Publisher Confirms mode](examples/publishConfirm.php)
- [Automatic returns processing in Publisher Confirms mode and mandatory flag enabled](examples/explicitReturn.php)
- [A more convenient way to work with transactions](examples/transactional.php)
- [Consume messages as concurrent iterator](examples/consumeIterator.php)
- [Consume messages in batch](examples/consumeBatch.php)
- [Native support for RPC](#rpc)

## Installation

```shell
composer require thesis/amqp
```

## Usage

- [Configuration](#configuration)
- [Client](#client)
- [Channel](#channel)
- [Publish a message](#publish-a-message)
- [Safety](#safety)
- [Consume a batch of messages](#consume-a-batch-of-messages)
- [Confirms](#confirms)
- [Explicit returns](#explicit-returns)
- [RPC](#rpc)
- [License](#license)

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

### Publish a message

There are notable changes here compared to other libraries.
- First, the message is an object.
- Secondly, all special headers like `correlationId`, `expiration`, `messageId` and so on are placed in the properties of this object, so you don't have to pass them through user headers and remember how keys should be named.

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

### Safety

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

### Consume a batch of messages

Although AMQP doesn't have a native way to receive messages in batches, we can achieve this using two operations — `basic.qos(count: N)` and `basic.ack(multiple: true)` on the last message. `basic.qos` limits the number of messages the AMQP server can push to our consumer, and this number should match the batch size.
`basic.ack(multiple: true)` allows us to send a single acknowledgment for the entire batch. You don’t need to implement this yourself — it's included with this library.
Simply use `Channel::consumeBatch` and pass a callback. As an argument, you’ll receive a `ConsumeBatch` instance, on which you can call `ack` or `nack`.
Note that you don’t need to call these functions on individual `DeliveryMessage` — only on the `ConsumeBatch`!

However, since it may take a while to fill a batch, you can specify a `timeout`. This way, you'll receive a non-empty batch either when the required number of messages is collected or when the timer expires — whichever comes first.
See the [example](examples/consumeBatch.php): you'll see two batches there — one will arrive immediately because the queue already contains enough messages and the second will arrive after a 1-second wait, consisting of just 3 messages.

Since `basic.qos(count: N)` is a crucial requirement for implementing batching, the `consumeBatch` and `consumeBatchIterator` methods call it automatically.
**You don’t need to call `Channel::qos` yourself!**

### Confirms

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

The `PublishConfirmation::await` will return `PublishResult` enum that can be in one of the `Acked, Nacked, Canceled, Waiting, Unrouted` states.

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

### Explicit returns

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

> This mechanism only works if `publisher confirms` are enabled. Without them the library cannot track which messages were successfully published to queues, because no frame will receive.

### RPC

Although AMQP doesn't provide a native way to perform RPC, there is a documented [algorithm](https://www.rabbitmq.com/docs/direct-reply-to) that uses the `reply-to` and `correlation-id` headers to implement it.
Since this algorithm can be difficult to implement for inexperienced users — especially given the asynchronous nature of our driver — our library handles it for you.
An example of how to use it can be found [here](examples/rpc.php).

Our `Rpc` will create a temporary queue named like `thesis.rpc.{random}` (you can inject your name using `RpcConfig`) and include its name in the `reply-to` header, along with a unique identifier in the `correlation-id` header, which your consumers should use to send the response.
In this case, it's more accurate to refer to your consumers as `responders`. These responders should consume messages from the durable `some_queue` in `noAck` mode and send responses.

To avoid manually filling in response headers or figuring out the correct reply queue, you can use the `DeliveryMessage::reply()` method, which will automatically send the message back to the appropriate queue. Here's how your responders should look:

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Message;
use function Amp\trapSignal;

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Client(Config::default());
$channel = $client->channel();

$channel->consume(
    callback: static function (DeliveryMessage $delivery): void {
        $delivery->reply(new Message("Request '{$delivery->message->body}' handled."));
    },
    queue: 'some_queue',
    noAck: true,
);

trapSignal([\SIGINT, \SIGTERM]);

$client->disconnect();
```

Since responders may be unavailable, we risk "hanging" indefinitely if we don't control the request execution time — just like in any `HTTP/gRPC` client.
You can configure a global timeout using `RpcConfig` as follows:

```php
<?php

declare(strict_types=1);

use Thesis\Amqp\Client;
use Thesis\Amqp\Config;
use Thesis\Amqp\RpcConfig;
use Thesis\Time\TimeSpan;
use Thesis\Amqp\Rpc;

$client = new Client(Config::default());
$rpc = new Rpc($client, new RpcConfig(timeout: TimeSpan::fromSeconds(5)));
```

Or you can specify a specific `Cancellation` for a request (which can be a signal or a timeout):

```php
<?php

declare(strict_types=1);

use Amp\TimeoutCancellation;
use Thesis\Amqp\Message;

echo $rpc
    ->request(
        message: new Message("Request#{$i}"),
        routingKey: 'some_queue',
        cancellation: new TimeoutCancellation(2),
    )
    ->body;
```

> The `Rpc` implements idempotency: if multiple requests with the same `correlationId` arrive simultaneously, they will receive the same result from the very first request.


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
