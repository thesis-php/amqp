<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Delivery;

/**
 * @internal
 */
final class ConsumerTagGenerator
{
    private const int TAG_LENGTH_MAX = 0xFF;
    private const string PACKAGE_NAME = 'thesis/amqp';

    /** @var non-empty-string */
    private readonly string $infix;

    /** @var non-negative-int */
    private int $consumerId = 0;

    public function __construct()
    {
        $command = $_SERVER['argv'][0] ?? null; // @phpstan-ignore offsetAccess.nonOffsetAccessible
        $this->infix = \is_string($command) && $command !== '' ? $command : self::PACKAGE_NAME;
    }

    /**
     * @return non-empty-string
     */
    public function next(): string
    {
        $prefix = 'ctag-';
        $infix = $this->infix;
        $suffix = \sprintf('-%d', ++$this->consumerId);

        if (\strlen($prefix) + \strlen($infix) + \strlen($suffix) > self::TAG_LENGTH_MAX) {
            $infix = self::PACKAGE_NAME;
        }

        return "{$prefix}{$infix}{$suffix}";
    }

    /**
     * @return non-empty-string
     */
    public function select(string $consumerTag): string
    {
        if ($consumerTag === '') {
            $consumerTag = $this->next();
        }

        return $consumerTag;
    }
}
