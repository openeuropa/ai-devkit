<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests\Composer;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Script\Event;
use OpenEuropa\AiDevkit\Composer\Plugin;
use OpenEuropa\AiDevkit\Tests\TempProjectTrait;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    use TempProjectTrait;

    public function testRunsBeforeUpstreamOnInstallAndUpdate(): void
    {
        $events = Plugin::getSubscribedEvents();
        foreach (['post-install-cmd', 'post-update-cmd'] as $event) {
            $entry = $events[$event];
            \assert(\is_array($entry));
            [$method, $priority] = $entry;
            self::assertSame('onPostCommand', $method);
            self::assertGreaterThan(0, $priority, 'must run before drupal/ai_best_practices syncs skills (priority 0)');
        }
    }

    public function testSkipsWhenEnvSaysSo(): void
    {
        putenv('OE_AI_DEVKIT_SKIP_SYNC=1');
        $root = $this->tempRoot('vendor');
        (new Plugin())->onPostCommand($this->event($root));
        putenv('OE_AI_DEVKIT_SKIP_SYNC');
        self::assertFileDoesNotExist($root.'/.kiro');
        self::assertFileDoesNotExist($root.'/ai_best_practices.yaml');
    }

    public function testWiresAConsumerAndPointsUpstreamSkillSyncAtKiro(): void
    {
        $root = $this->tempRoot('vendor/drupal/ai_best_practices');
        file_put_contents($root.'/vendor/drupal/ai_best_practices/ai_best_practices.yaml.dist', "skills:\n  output_directory: .agents/skills\n");
        $io = new BufferIO();
        (new Plugin())->onPostCommand($this->event($root, $io));
        self::assertFileExists($root.'/.kiro/steering/oe-working-style.md');
        self::assertFileExists($root.'/.kiro/hooks/oe-guardrails.json');
        self::assertSame("skills:\n  output_directory: .kiro/skills\n", file_get_contents($root.'/ai_best_practices.yaml'));
        self::assertStringContainsString('wrote .kiro/steering/oe-working-style.md', $io->getOutput());
    }

    public function testWarnsWhenTheProjectSyncsSkillsElsewhere(): void
    {
        $root = $this->tempRoot('vendor');
        file_put_contents($root.'/ai_best_practices.yaml', "skills:\n  output_directory: .agents/skills\n");
        $io = new BufferIO();
        (new Plugin())->onPostCommand($this->event($root, $io));
        self::assertStringContainsString('Kiro reads skills only from .kiro/skills', $io->getOutput());
    }

    private function event(string $root, ?IOInterface $io = null): Event
    {
        $composer = new Composer();
        $config = new Config(false, $root);
        $config->merge(['config' => ['vendor-dir' => $root.'/vendor']]);
        $composer->setConfig($config);

        return new Event('post-install-cmd', $composer, $io ?? new NullIO());
    }
}
