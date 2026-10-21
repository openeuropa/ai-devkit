<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\PackageRoot;
use PHPUnit\Framework\TestCase;

final class SkillsContentTest extends TestCase
{
    public function testEverySkillHasValidFrontmatterAndAnEvalSuite(): void
    {
        $names = array_map(fn (string $f) => basename(\dirname($f)), glob(PackageRoot::path().'/skills/*/SKILL.md') ?: []);
        self::assertContains('oe-project-wiki', $names);
        foreach ($names as $name) {
            $md = (string) file_get_contents(PackageRoot::path()."/skills/$name/SKILL.md");
            self::assertMatchesRegularExpression('/^---\nname: '.preg_quote($name, '/').'\ndescription: .+\n---\n/s', $md, "$name frontmatter");
            self::assertLessThanOrEqual(500, substr_count($md, "\n"), "$name body under 500 lines");
            self::assertStringStartsWith('oe-', $name);
            self::assertFileExists(PackageRoot::path()."/evals/$name/static-checks.json");
            self::assertFileExists(PackageRoot::path()."/evals/$name/README.md");
        }
    }
}
