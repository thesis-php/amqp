<?php

declare(strict_types=1);

namespace Thesis\Amqp;

use Revolt\EventLoop;

/**
 * @api
 * @phpstan-type Events = Event\ConnectionBlocked|Event\ConnectionUnblocked
 */
final class EventDispatcher
{
    /**
     * @var array<class-string<Events>, non-empty-list<callable(Events): void>>
     */
    private array $listeners = [];

    /**
     * @template TEvent of Events
     * @param class-string<TEvent>|non-empty-list<class-string<TEvent>> $events
     * @param callable(TEvent): void $listener
     */
    public function listen(string|array $events, callable $listener): self
    {
        $dispatcher = clone $this;

        foreach ((array) $events as $event) {
            /** @phpstan-ignore assign.propertyType */
            $dispatcher->listeners[$event][] = $listener;
        }

        return $dispatcher;
    }

    /**
     * @param Events $event
     */
    public function dispatch(object $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            /** @phpstan-ignore argument.type */
            EventLoop::queue($listener, $event);
        }
    }
}
