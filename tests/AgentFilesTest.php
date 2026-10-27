<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\Guardrail\Guardrail;
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
        // A different model from the implementer's, so review does not share the author's blind spots.
        self::assertSame('claude-sonnet-5', $agent['model']);
        self::assertContains('skill://.kiro/skills/oe-qa-gate/SKILL.md', $agent['resources']);
        $denies = array_filter($agent['permissions']['rules'], fn (array $r) => 'deny' === $r['effect'] && 'fs_write' === $r['capability']);
        self::assertNotEmpty($denies, 'reviewer must deny writes');
        self::assertFileExists(PackageRoot::path().'/agents/oe-reviewer.prompt.md');
    }

    /** Some Kiro builds read the matcher only as a regex against the tool ID, so test it that way. */
    public function testGuardrailHookSelectsTheShellTool(): void
    {
        $hook = json_decode((string) file_get_contents(PackageRoot::path().'/hooks/oe-guardrails.json'), true, 512, JSON_THROW_ON_ERROR)['hooks'][0];
        $composer = json_decode((string) file_get_contents(PackageRoot::path().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PreToolUse', $hook['trigger']);
        self::assertSame('vendor/bin/'.basename($composer['bin'][0]), $hook['action']['command']);
        foreach (Guardrail::SHELL_TOOLS as $tool) {
            self::assertSame(1, preg_match('/'.str_replace('/', '\/', $hook['matcher']).'/', $tool), "matcher must select {$tool}");
        }
    }

    /** Kiro CLI v3 removed toolsSettings, allowedTools and denyByDefault; the v2 engine is not supported. */
    public function testReviewerUsesOnlyV3AgentFields(): void
    {
        $agent = json_decode((string) file_get_contents(PackageRoot::path().'/agents/oe-reviewer.json'), true, 512, JSON_THROW_ON_ERROR);
        $v3Fields = ['name', 'description', 'prompt', 'model', 'tools', 'excludedTools', 'permissions', 'resources', 'mcpServers', 'includeMcpJson', 'includePowers', 'hooks', 'welcomeMessage'];
        self::assertSame([], array_values(array_diff(array_keys($agent), $v3Fields)));
        foreach ($agent['permissions']['rules'] as $rule) {
            self::assertSame([], array_values(array_diff(array_keys($rule), ['capability', 'match', 'exclude', 'effect'])));
            self::assertContains($rule['effect'], ['allow', 'ask', 'deny']);
        }
    }

    /** In v3 a deny rule wins over an allow rule, so a catch-all deny must exclude what the reviewer may run. */
    public function testCatchAllShellDenyLeavesAllowedCommandsRunnable(): void
    {
        $agent = json_decode((string) file_get_contents(PackageRoot::path().'/agents/oe-reviewer.json'), true, 512, JSON_THROW_ON_ERROR);
        $shell = array_filter($agent['permissions']['rules'], fn (array $r) => 'shell' === $r['capability']);
        $allowed = array_merge(...array_map(fn (array $r) => $r['match'], array_values(array_filter($shell, fn (array $r) => 'allow' === $r['effect']))));
        $catchAll = array_filter($shell, fn (array $r) => 'deny' === $r['effect'] && \in_array('*', $r['match'], true));
        self::assertNotEmpty($allowed);
        self::assertCount(1, $catchAll, 'reviewer must deny any other shell command');
        foreach ($catchAll as $rule) {
            self::assertSame([], array_values(array_diff($allowed, $rule['exclude'] ?? [])), 'allowed commands must be excluded from the catch-all deny');
        }
    }
}
