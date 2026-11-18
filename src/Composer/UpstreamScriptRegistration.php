<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Composer;

use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Drupal\AiBestPractices\Config\AiBestPracticesConfigLoader;
use Drupal\AiBestPractices\Console\AiBestPracticesApplication;

/**
 * Registers upstream's script before its lossy composer.json rewrite (OEL-5208).
 */
final class UpstreamScriptRegistration
{
    /**
     * Returns whether composer.json changed. Remove once upstream preserves JSON types.
     */
    public static function ensure(string $root): bool
    {
        $path = $root.'/composer.json';
        if (getenv('AI_BEST_PRACTICES_SKIP_COMPOSER_MUTATION') || !is_file($path)) {
            return false;
        }
        $config = AiBestPracticesConfigLoader::load($root);
        if (false === ($config['auto_register_composer_scripts'] ?? true)) {
            return false;
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new \RuntimeException('Could not read '.$path);
        }
        $data = JsonFile::parseJson($contents, $path);
        $name = AiBestPracticesApplication::COMPOSER_SCRIPT_NAME;
        $callback = AiBestPracticesApplication::COMPOSER_SCRIPT_CALLBACK;
        $removeLegacy = ($data['scripts']['abp'] ?? null) === $callback;
        $addScript = !isset($data['scripts'][$name]);
        if (!$removeLegacy && !$addScript) {
            return false;
        }

        $json = new JsonManipulator($contents);
        if (($removeLegacy && !$json->removeSubNode('scripts', 'abp'))
            || ($addScript && !$json->addSubNode('scripts', $name, $callback))) {
            throw new \RuntimeException('Could not safely register '.$name.' in '.$path);
        }
        if (false === file_put_contents($path, $json->getContents())) {
            throw new \RuntimeException('Could not write '.$path);
        }

        return true;
    }
}
