<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use PHPUnit\Framework\TestCase;

final class ScaffoldTest extends TestCase
{
    public function testComposerJsonDeclaresAPlugin(): void
    {
        $json = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('openeuropa/ai-devkit', $json['name']);
        self::assertSame('composer-plugin', $json['type']);
        self::assertSame('OpenEuropa\\AiDevkit\\Composer\\Plugin', $json['extra']['class']);
        self::assertSame('EUPL-1.2', $json['license']);
        self::assertArrayHasKey('bin', $json);
        self::assertContains('bin/oe-guardrail', $json['bin']);
    }
}
