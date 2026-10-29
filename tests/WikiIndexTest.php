<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\PackageRoot;
use PHPUnit\Framework\TestCase;

/**
 * Soft completeness guard for this repo's own docs/wiki/: every page under an
 * area folder must be linked from index.md. adr-0002 rules out a hard CI gate
 * on the wiki; this only checks the index stays in step with its pages, which
 * is cheap and not gameable the way a "a file was touched" check would be.
 */
final class WikiIndexTest extends TestCase
{
    public function testIndexListsEveryWikiPage(): void
    {
        $wiki = PackageRoot::path().'/docs/wiki';
        if (!is_dir($wiki)) {
            self::markTestSkipped('no docs/wiki/ in this checkout');
        }

        $index = $wiki.'/index.md';
        self::assertFileExists($index, 'docs/wiki/index.md is the entry point');
        $indexText = (string) file_get_contents($index);

        // Every markdown page under an area folder (one level below docs/wiki/),
        // referenced by its wiki-relative path, must appear in the index.
        $pages = glob($wiki.'/*/*.md') ?: [];
        self::assertNotEmpty($pages, 'the wiki has at least one page');
        foreach ($pages as $page) {
            $rel = substr($page, \strlen($wiki) + 1);
            self::assertStringContainsString($rel, $indexText, "index.md links $rel");
        }
    }
}
