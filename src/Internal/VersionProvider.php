<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Composer\InstalledVersions;

/**
 * @internal
 */
final class VersionProvider
{
    /** @var non-empty-string */
    private const string DEFAULT_VERSION = 'dev';

    /** @var non-empty-string */
    private const string PACKAGE_NAME = 'thesis/amqp';

    /** @var ?non-empty-string */
    private static ?string $version = null;

    /**
     * @return non-empty-string
     */
    public static function provide(): string
    {
        return self::$version ??= (static function (): string {
            $version = self::DEFAULT_VERSION;
            if (InstalledVersions::isInstalled(self::PACKAGE_NAME) && ($prettyVersion = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME)) !== null) {
                $version = $prettyVersion ?: self::DEFAULT_VERSION;
            }

            return $version;
        })();
    }
}
