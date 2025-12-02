<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Thesis\Amqp\Internal\Delivery\ConsumerTagGenerator;

#[CoversClass(ConsumerTagGenerator::class)]
final class ConsumerTagGeneratorTest extends TestCase
{
    /**
     * @param list<string> $argv
     */
    #[TestWith([[], 'ctag-thesis/amqp-1'])]
    #[TestWith([['/kafkiansky/amqp/vendor/bin/phpunit'], 'ctag-/kafkiansky/amqp/vendor/bin/phpunit-1'])]
    #[TestWith([[''], 'ctag-thesis/amqp-1'])]
    #[RunInSeparateProcess]
    public function testNext(array $argv, string $expected): void
    {
        $_SERVER['argv'] = $argv;
        $gen = new ConsumerTagGenerator();

        $tag = $gen->next();

        self::assertSame($expected, $tag);
    }

    #[RunInSeparateProcess]
    public function testSelect(): void
    {
        $_SERVER['argv'] = [];
        $gen = new ConsumerTagGenerator();

        self::assertSame('ctag-thesis/amqp-1', $gen->next());
        self::assertSame('custom', $gen->select('custom'));
        self::assertSame('ctag-thesis/amqp-2', $gen->next());
    }
}
