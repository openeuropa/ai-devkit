<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\PackageRoot;
use PHPUnit\Framework\TestCase;

final class PackageRootTest extends TestCase
{
    public function testPathIsTheRepositoryRoot(): void
    {
        self::assertFileExists(PackageRoot::path().'/composer.json');
        self::assertSame(realpath(__DIR__.'/..'), PackageRoot::path());
    }
}
