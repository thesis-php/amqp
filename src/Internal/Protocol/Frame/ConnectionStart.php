<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal\Protocol\Frame;

use Thesis\Amqp\Internal\Io;
use Thesis\Amqp\Internal\Protocol\Frame;

/**
 * @internal
 * @phpstan-type ServerProperties = array{
 *     version: string,
 *     platform: string,
 *     cluster_name: string,
 *     capabilities?: array{
 *        publisher_confirms?: bool,
 *        direct_reply_to?: bool,
 *        per_consumer_qos?: bool,
 *     },
 * }
 */
final readonly class ConnectionStart implements Frame
{
    /**
     * @param non-negative-int $versionMajor
     * @param non-negative-int $versionMinor
     * @param ServerProperties $serverProperties
     * @param list<string> $mechanisms
     */
    public function __construct(
        public int $versionMajor,
        public int $versionMinor,
        public array $serverProperties,
        public array $mechanisms = [],
        public string $locales = '',
    ) {}

    public static function read(Io\ReadBytes $reader): self
    {
        [$versionMajor, $versionMinor] = [$reader->readUint8(), $reader->readUint8()];

        /** @var ServerProperties $serverProperties */
        $serverProperties = $reader->readTable();

        return new self(
            $versionMajor,
            $versionMinor,
            $serverProperties,
            explode(' ', $reader->readText()),
            $reader->readText(),
        );
    }

    public function write(Io\WriteBytes $writer): void
    {
        $writer
            ->writeUint8($this->versionMajor)
            ->writeUint8($this->versionMinor)
            ->writeTable($this->serverProperties)
            ->writeText(implode(' ', $this->mechanisms))
            ->writeText($this->locales);
    }
}
