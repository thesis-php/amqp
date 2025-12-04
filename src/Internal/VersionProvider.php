<?php

declare(strict_types=1);

namespace Thesis\Amqp\Internal;

use Composer\InstalledVersions;

/**
 * @internal
 */
final class VersionProvider
{
    private const string DEFAULT_VERSION = 'dev';
    private const string PACKAGE_NAME = 'thesis/amqp';

    /** @var ?non-empty-string */
    private static ?string $version = null;

    /**
     * @return non-empty-string
     */
    public static function provide(): string
    {
        return self::$version ??= self::doProvide();
    }

    /** @return non-empty-string */
    private static function doProvide(): string
    {
        if (!InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return self::DEFAULT_VERSION;
        }

        $prettyVersion = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);

        if ($prettyVersion !== null && $prettyVersion !== '') {
            return $prettyVersion;
        }

        return self::DEFAULT_VERSION;
    }

    private function __construct() {}
}
