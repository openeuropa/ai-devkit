<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit;

/**
 * Absolute path of this package, whether it runs from a checkout or from vendor/.
 */
final class PackageRoot
{
    public static function path(): string
    {
        return \dirname(__DIR__);
    }
}
