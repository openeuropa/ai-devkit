<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Wiring;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Copies the devkit's Kiro files (steering, guardrail hook, reviewer agent) into a project's .kiro/.
 * Skills are not handled here: drupal/ai_best_practices syncs them, including this package's skills/.
 */
final class WiringInstaller
{
    /** Always-on steering must stay under this many lines in total, so it does not crowd the agent's context. */
    public const STEERING_BUDGET = 200;

    /** @var array<string, string> project path => package path, refreshed on every run */
    private const MANAGED = [
        '.kiro/hooks/oe-guardrails.json' => 'hooks/oe-guardrails.json',
        '.kiro/agents/oe-reviewer.json' => 'agents/oe-reviewer.json',
        '.kiro/agents/oe-reviewer.prompt.md' => 'agents/oe-reviewer.prompt.md',
    ];

    /**
     * Project-owned files, written only when missing. The two steering files are the ones
     * oe-guardrails.md and oe-rtk.md include; they are always-on because Kiro CLI v3 does
     * not expand those includes.
     *
     * @var array<string, string> project path => package path
     */
    private const CREATE_ONCE = [
        '.kiro/.gitignore' => 'templates/kiro.gitignore',
        '.kiro/steering/project-guardrails.md' => 'templates/project-guardrails.md',
        '.kiro/steering/project-rtk.md' => 'templates/project-rtk.md',
        // Commented examples for the guardrail's project inputs; inert until a line is enabled.
        '.kiro/oe-guardrail-patterns.txt' => 'templates/oe-guardrail-patterns.txt',
        '.aiignore' => 'templates/aiignore',
    ];

    public function __construct(private readonly string $packageRoot)
    {
    }

    /**
     * Total line count of the oe-* steering files in a directory.
     */
    public static function steeringLines(string $steeringDir): int
    {
        $lines = 0;
        foreach (glob($steeringDir.'/oe-*.md') ?: [] as $file) {
            $lines += \count(file($file) ?: []);
        }

        return $lines;
    }

    /**
     * @return list<string> project-relative paths written (unchanged files are skipped)
     */
    public function install(string $root): array
    {
        $files = [];
        foreach (glob($this->packageRoot.'/steering/oe-*.md') ?: [] as $file) {
            $files['.kiro/steering/'.basename($file)] = 'steering/'.basename($file);
        }
        $files += self::MANAGED;
        foreach (self::CREATE_ONCE as $dst => $src) {
            if (!file_exists($root.'/'.$dst)) {
                $files[$dst] = $src;
            }
        }

        $fs = new Filesystem();
        $written = [];
        foreach ($files as $dst => $src) {
            $content = (string) file_get_contents($this->packageRoot.'/'.$src);
            if (!is_file($root.'/'.$dst) || file_get_contents($root.'/'.$dst) !== $content) {
                $fs->dumpFile($root.'/'.$dst, $content);
                $written[] = $dst;
            }
        }

        return $written;
    }
}
