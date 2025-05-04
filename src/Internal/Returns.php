<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Thesis\Amqp\DeliveryMessage;
use Thesis\Amqp\Internal\Delivery\DeliverySupervisor;

/**
 * @internal
 * @phpstan-type ReturnCallback = callable(DeliveryMessage): void
 */
final class Returns
{
    /** @var list<ReturnCallback> */
    private array $onReturnCallbacks = [];

    public function __construct(DeliverySupervisor $supervisor)
    {
        $callbacks = &$this->onReturnCallbacks;

        $supervisor->addReturnListener(static function (DeliveryMessage $delivery) use (&$callbacks): void {
            foreach ($callbacks as $callback) {
                $callback($delivery);
            }
        });

        $supervisor->addShutdownListener(static function () use (&$callbacks): void {
            $callbacks = [];
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
