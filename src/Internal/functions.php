<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use BcMath\Number;

/**
 * @internal
 * @param non-empty-string $query
 * @return array<non-empty-string, non-empty-string|non-empty-list<non-empty-string>>
 */
function parseQuery(string $query): array
{
    $items = [];

    foreach (queryIterator($query) as $name => $value) {
        if (!isset($items[$name])) {
            $items[$name] = $value;
        } else {
            /** @phpstan-ignore-next-line booleanNot.alwaysTrue */
            if (!\is_array($items[$name])) {
                $items[$name] = [$items[$name]];
            }

            $items[$name][] = $value;
        }
    }

    return $items;
}

/**
 * @internal
 * @param non-empty-string $query
 * @return \Generator<non-empty-string, non-empty-string>
 */
function queryIterator(string $query): \Generator
{
    $pairs = explode('&', $query);

    foreach ($pairs as $pair) {
        $it = explode('=', $pair, 2);

        if (\count($it) === 2) {
            [$name, $value] = [$it[0], urldecode($it[1])];

            if ($name !== '' && $value !== '') {
                yield $name => $value;
            }
        }
    }
}

/**
 * @internal
 * @param positive-int $length
 * @return iterable<non-empty-string>
 */
function chunks(string $v, int $length): iterable
{
    foreach (str_split($v, $length) as $chunk) {
        yield $chunk;
    }
}

/**
 * @internal
 * @return non-negative-int
 */
function deliveryTagToInt(Number $deliveryTag): int
{
    $deliveryTag = (int) $deliveryTag->value;
    \assert($deliveryTag >= 0);

    return $deliveryTag;
}
