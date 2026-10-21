<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Wiring;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Points drupal/ai_best_practices' skill sync at `.kiro/skills`, the only place Kiro reads skills from.
 *
 * The file is upstream's (`ai_best_practices.yaml`); it is created here only when missing, from upstream's
 * own dist so its comments and defaults are kept. An existing file is never edited.
 */
final class SkillsOutputConfig
{
    /** Upstream's settings file, in the project root. */
    public const FILE_NAME = 'ai_best_practices.yaml';

    /** The directory Kiro reads skills from. */
    public const KIRO_SKILLS_DIR = '.kiro/skills';

    /** The file was missing and has been created. */
    public const CREATED = 'created';

    /** The file exists and already points at .kiro/skills. */
    public const OK = 'ok';

    /** The file exists and points somewhere else. */
    public const ELSEWHERE = 'elsewhere';

    /** The file exists but is not valid YAML. */
    public const MALFORMED = 'malformed';

    /**
     * Creates ai_best_practices.yaml when it is missing and reports the state of an existing one.
     *
     * @param string $root         the project root
     * @param string $upstreamDist path to upstream's ai_best_practices.yaml.dist, used as the template
     *
     * @return self::CREATED|self::OK|self::ELSEWHERE|self::MALFORMED
     */
    public static function ensure(string $root, string $upstreamDist): string
    {
        $path = $root.'/'.self::FILE_NAME;
        if (!file_exists($path)) {
            $dist = is_file($upstreamDist) ? (string) file_get_contents($upstreamDist) : '';
            $count = 0;
            $content = preg_replace('/^(\s*output_directory:\s*)\S+/m', '${1}'.self::KIRO_SKILLS_DIR, $dist, 1, $count);
            file_put_contents($path, 1 === $count ? $content : "skills:\n  output_directory: ".self::KIRO_SKILLS_DIR."\n");

            return self::CREATED;
        }
        try {
            $config = Yaml::parse((string) file_get_contents($path));
        } catch (ParseException) {
            return self::MALFORMED; // Upstream refuses to sync it too; the file is the project's to fix.
        }

        return self::KIRO_SKILLS_DIR === ($config['skills']['output_directory'] ?? null) ? self::OK : self::ELSEWHERE;
    }
}
