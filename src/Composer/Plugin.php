<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use OpenEuropa\AiDevkit\PackageRoot;
use OpenEuropa\AiDevkit\Wiring\SkillsOutputConfig;
use OpenEuropa\AiDevkit\Wiring\WiringInstaller;

/**
 * After every install and update: points upstream's skill sync at .kiro/skills and places the Kiro files.
 *
 * Runs before drupal/ai_best_practices' own post-install/update handler (priority 0), so the skill sync it
 * triggers already sees `skills.output_directory: .kiro/skills` on the very first install.
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /** Event priority, higher than upstream's 0 so this plugin runs first. */
    private const PRIORITY = 10;

    /**
     * Does nothing: the work runs on the post-install and post-update events.
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Does nothing: the plugin keeps no state to tear down.
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * Leaves the project's files in place when the package is removed.
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Files placed in the project (.kiro/, ai_best_practices.yaml) are project-owned; nothing is removed.
    }

    /**
     * Runs wireProject() after every install and update.
     *
     * @return array<string, array{string, int}> event name => [method, priority]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-install-cmd' => ['wireProject', self::PRIORITY],
            'post-update-cmd' => ['wireProject', self::PRIORITY],
        ];
    }

    /**
     * Points upstream's skill sync at .kiro/skills and installs the Kiro files into the project.
     *
     * @param Event $event the post-install-cmd or post-update-cmd event
     */
    public function wireProject(Event $event): void
    {
        if (getenv('OE_AI_DEVKIT_SKIP_SYNC')) {
            return;
        }
        $vendorDir = rtrim((string) $event->getComposer()->getConfig()->get('vendor-dir'), '/');
        $root = \dirname($vendorDir);
        if ('' === $vendorDir || realpath($root) === realpath(PackageRoot::path())) {
            return; // Never wire this package's own checkout.
        }
        $io = $event->getIO();
        $io->write('<info>OpenEuropa AI DevKit:</info>');

        $status = SkillsOutputConfig::ensure($root, $vendorDir.'/drupal/ai_best_practices/ai_best_practices.yaml.dist');
        if (SkillsOutputConfig::CREATED === $status) {
            $io->write('  wrote '.SkillsOutputConfig::FILE_NAME.' (skills sync to '.SkillsOutputConfig::KIRO_SKILLS_DIR.')');
        } elseif (SkillsOutputConfig::ELSEWHERE === $status) {
            $io->writeError('<warning>  Kiro reads skills only from '.SkillsOutputConfig::KIRO_SKILLS_DIR.'; set skills.output_directory to it in '.SkillsOutputConfig::FILE_NAME.'.</warning>');
        } elseif (SkillsOutputConfig::MALFORMED === $status) {
            $io->writeError('<warning>  '.SkillsOutputConfig::FILE_NAME.' is not valid YAML; fix it, then run composer drupal-ai skills-sync.</warning>');
        }

        foreach ((new WiringInstaller(PackageRoot::path()))->install($root) as $path) {
            $io->write('  wrote '.$path);
        }
    }
}
