<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Returns;

use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;

/**
 * @internal
 * @phpstan-type ReturnCallback = callable(DeliveryMessage): void
 */
final class ReturnListener
{
    /** @var list<ReturnCallback> */
    private array $onReturnCallbacks = [];

    public function __construct(DeliverySupervisor $supervisor)
    {
        $callbacks = &$this->onReturnCallbacks;

        $supervisor->addReturnListener(static function (DeliveryMessage $delivery) use (&$callbacks): void {
            if (!isset($delivery->message->headers[FutureBoundedReturnListener::TRACE_HEADER_KEY])) {
                foreach ($callbacks as $callback) {
                    $callback($delivery);
                }
            }
        });
    }

    /**
     * @param ReturnCallback $callback
     */
    public function addReturnCallback(callable $callback): void
    {
        $this->onReturnCallbacks[] = $callback;
    }
}
