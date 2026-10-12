<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit;

/**
 * Absolute path of this package, whether it runs from a checkout or from vendor/.
 */
final class PackageRoot
{
    /**
     * Returns the package root directory.
     *
     * @return string the directory that holds this package's composer.json
     */
    public static function path(): string
    {
        return \dirname(__DIR__);
    }
}
