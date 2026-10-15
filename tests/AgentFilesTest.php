<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\PackageRoot;
use PHPUnit\Framework\TestCase;

final class AgentFilesTest extends TestCase
{
    public function testReviewerIsReadOnlyAndLoadsSkillsExplicitly(): void
    {
        $agent = json_decode((string) file_get_contents(PackageRoot::path().'/agents/oe-reviewer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('oe-reviewer', $agent['name']);
        self::assertSame('file://./oe-reviewer.prompt.md', $agent['prompt']);
        self::assertSame(['read', 'shell'], $agent['tools']);
        self::assertArrayNotHasKey('model', $agent);
        self::assertContains('skill://.kiro/skills/oe-qa-gate/SKILL.md', $agent['resources']);
        $denies = array_filter($agent['permissions']['rules'], fn (array $r) => 'deny' === $r['effect'] && 'fs_write' === $r['capability']);
        self::assertNotEmpty($denies, 'reviewer must deny writes');
        self::assertFileExists(PackageRoot::path().'/agents/oe-reviewer.prompt.md');
    }
}
