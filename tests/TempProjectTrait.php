<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Throwaway project directories for tests, removed after each test.
 */
trait TempProjectTrait
{
    /** @var list<string> */
    private array $tempRoots = [];

    /**
     * Creates and returns an empty temporary directory (with optional subdirectories).
     */
    protected function tempRoot(string ...$subdirs): string
    {
        $root = sys_get_temp_dir().'/oe-ai-'.uniqid();
        mkdir($root, 0777, true);
        foreach ($subdirs as $subdir) {
            mkdir($root.'/'.$subdir, 0777, true);
        }
        $this->tempRoots[] = $root;

        return $root;
    }

    /**
     * @after
     */
    protected function removeTempRoots(): void
    {
        (new Filesystem())->remove($this->tempRoots);
        $this->tempRoots = [];
    }
}
