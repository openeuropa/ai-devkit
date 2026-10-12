<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests\Wiring;

use OpenEuropa\AiDevkit\PackageRoot;
use OpenEuropa\AiDevkit\Tests\TempProjectTrait;
use OpenEuropa\AiDevkit\Wiring\WiringInstaller;
use PHPUnit\Framework\TestCase;

final class WiringInstallerTest extends TestCase
{
    use TempProjectTrait;

    private const MANAGED = [
        '.kiro/steering/oe-guardrails.md',
        '.kiro/steering/oe-rtk.md',
        '.kiro/steering/oe-working-style.md',
        '.kiro/steering/oe-writing-conventions.md',
        '.kiro/hooks/oe-guardrails.json',
        '.kiro/agents/oe-reviewer.json',
        '.kiro/agents/oe-reviewer.prompt.md',
        '.kiro/.gitignore',
        '.kiro/steering/project-guardrails.md',
        '.kiro/steering/project-rtk.md',
        '.kiro/oe-guardrail-patterns.txt',
        '.aiignore',
    ];

    public function testInstallsTheKiroFilesAndIsIdempotent(): void
    {
        $root = $this->tempRoot();
        $installer = new WiringInstaller(PackageRoot::path());
        self::assertSame(self::MANAGED, $installer->install($root));
        foreach (self::MANAGED as $path) {
            self::assertFileExists($root.'/'.$path);
        }
        self::assertSame([], $installer->install($root), 'a second run writes nothing');
    }

    public function testManagedFilesAreRefreshedButTheGitignoreIsTheProjects(): void
    {
        $root = $this->tempRoot();
        $installer = new WiringInstaller(PackageRoot::path());
        $installer->install($root);
        file_put_contents($root.'/.kiro/steering/oe-rtk.md', 'edited');
        file_put_contents($root.'/.kiro/.gitignore', "mine\n");
        self::assertSame(['.kiro/steering/oe-rtk.md'], $installer->install($root));
        self::assertSame("mine\n", file_get_contents($root.'/.kiro/.gitignore'));
    }

    public function testDoesNotTouchProjectSteeringFiles(): void
    {
        $root = $this->tempRoot('.kiro/steering');
        file_put_contents($root.'/.kiro/steering/project-guardrails.md', 'project rules');
        (new WiringInstaller(PackageRoot::path()))->install($root);
        self::assertSame('project rules', file_get_contents($root.'/.kiro/steering/project-guardrails.md'));
    }

    public function testEveryFileTheSteeringIncludesExistsAfterInstall(): void
    {
        $root = $this->tempRoot();
        (new WiringInstaller(PackageRoot::path()))->install($root);
        $includes = [];
        foreach (glob(PackageRoot::path().'/steering/oe-*.md') ?: [] as $file) {
            preg_match_all('/#\[\[file:([^\]]+)\]\]/', (string) file_get_contents($file), $m);
            $includes = [...$includes, ...$m[1]];
        }
        self::assertNotEmpty($includes);
        foreach ($includes as $include) {
            self::assertFileExists($root.'/'.$include);
        }
    }

    public function testProjectSteeringStartersAreAlwaysOnAndCreatedOnlyOnce(): void
    {
        $root = $this->tempRoot();
        $installer = new WiringInstaller(PackageRoot::path());
        $installer->install($root);
        foreach (['project-guardrails.md', 'project-rtk.md'] as $name) {
            // Kiro CLI v3 does not expand the includes, so the file must load on its own.
            self::assertStringStartsWith("---\ninclusion: always\n---\n", (string) file_get_contents($root.'/.kiro/steering/'.$name));
            file_put_contents($root.'/.kiro/steering/'.$name, 'project content');
        }
        self::assertSame([], $installer->install($root));
        self::assertSame('project content', file_get_contents($root.'/.kiro/steering/project-rtk.md'));
    }

    public function testGuardrailExamplesAreInertUntilUncommentedAndValid(): void
    {
        $root = $this->tempRoot();
        (new WiringInstaller(PackageRoot::path()))->install($root);
        foreach (['.kiro/oe-guardrail-patterns.txt', '.aiignore'] as $file) {
            $lines = file($root.'/'.$file, \FILE_IGNORE_NEW_LINES) ?: [];
            $active = array_filter(array_map('trim', $lines), fn (string $l) => '' !== $l && !str_starts_with($l, '#'));
            self::assertSame([], $active, "$file ships no active entry");
            self::assertNotEmpty(preg_grep('/^# \S/', $lines), "$file shows at least one example");
        }
        foreach (preg_grep('~^# /~', file($root.'/.kiro/oe-guardrail-patterns.txt', \FILE_IGNORE_NEW_LINES) ?: []) ?: [] as $example) {
            self::assertNotFalse(@preg_match(substr($example, 2), ''), "example pattern compiles: $example");
        }
    }

    public function testSteeringBudget(): void
    {
        self::assertLessThanOrEqual(WiringInstaller::STEERING_BUDGET, WiringInstaller::steeringLines(PackageRoot::path().'/steering'));
    }
}
