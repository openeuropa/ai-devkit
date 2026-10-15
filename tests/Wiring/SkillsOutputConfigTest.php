<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests\Wiring;

use OpenEuropa\AiDevkit\Tests\TempProjectTrait;
use OpenEuropa\AiDevkit\Wiring\SkillsOutputConfig;
use PHPUnit\Framework\TestCase;

final class SkillsOutputConfigTest extends TestCase
{
    use TempProjectTrait;

    public function testCreatesTheUpstreamConfigFromItsDistPointingAtKiro(): void
    {
        $root = $this->tempRoot();
        $dist = $root.'/dist.yaml';
        file_put_contents($dist, "# comment kept\nskills:\n  # Output directory\n  output_directory: .agents/skills\n  discover_composer: true\n");
        self::assertSame(SkillsOutputConfig::CREATED, SkillsOutputConfig::ensure($root, $dist));
        self::assertSame("# comment kept\nskills:\n  # Output directory\n  output_directory: .kiro/skills\n  discover_composer: true\n", file_get_contents($root.'/ai_best_practices.yaml'));
    }

    public function testCreatesAMinimalConfigWhenTheDistIsMissing(): void
    {
        $root = $this->tempRoot();
        self::assertSame(SkillsOutputConfig::CREATED, SkillsOutputConfig::ensure($root, $root.'/no-dist.yaml'));
        self::assertSame("skills:\n  output_directory: .kiro/skills\n", file_get_contents($root.'/ai_best_practices.yaml'));
    }

    public function testLeavesAnExistingConfigAloneAndReportsWhetherItPointsAtKiro(): void
    {
        $root = $this->tempRoot();
        file_put_contents($root.'/ai_best_practices.yaml', "skills:\n  output_directory: .kiro/skills\n");
        self::assertSame(SkillsOutputConfig::OK, SkillsOutputConfig::ensure($root, $root.'/dist.yaml'));

        file_put_contents($root.'/ai_best_practices.yaml', "skills:\n  output_directory: .agents/skills\n");
        self::assertSame(SkillsOutputConfig::ELSEWHERE, SkillsOutputConfig::ensure($root, $root.'/dist.yaml'));
        self::assertSame("skills:\n  output_directory: .agents/skills\n", file_get_contents($root.'/ai_best_practices.yaml'));

        file_put_contents($root.'/ai_best_practices.yaml', "skills: [\n");
        self::assertSame(SkillsOutputConfig::ELSEWHERE, SkillsOutputConfig::ensure($root, $root.'/dist.yaml'), 'malformed YAML is left for upstream to report');
    }
}
