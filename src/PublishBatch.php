<?php

declare(strict_types=1);

namespace Thesis\Amqp;

/**
 * @api
 *
 * @template-implements \IteratorAggregate<array{Message, string, string, bool, bool}>
 */
final class PublishBatch implements \IteratorAggregate
{
    /** @var list<array{Message, string, string, bool, bool}> */
    private array $messages = [];

    public static function default(): self
    {
        return new self();
    }

    public function add(
        Message $message,
        string $exchange = '',
        string $routingKey = '',
        bool $mandatory = false,
        bool $immediate = false,
    ): self {
        $batch = clone $this;
        $batch->messages[] = [
            $message,
            $exchange,
            $routingKey,
            $mandatory,
            $immediate,
        ];

        return $batch;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->messages;
    }
}
