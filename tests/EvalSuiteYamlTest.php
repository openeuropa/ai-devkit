<?php

declare(strict_types=1);

namespace OpenEuropa\AiDevkit\Tests;

use OpenEuropa\AiDevkit\PackageRoot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the behavioural eval suites: the runner only parses these on a live
 * provider run, so a YAML break (e.g. an unquoted "Key: value" token in a plain
 * scalar) would otherwise ship unnoticed.
 */
final class EvalSuiteYamlTest extends TestCase
{
    /** @return iterable<string, array{string}> suite directory name => [path] */
    public static function suites(): iterable
    {
        foreach (glob(PackageRoot::path().'/evals/*/dataset.yaml') ?: [] as $dataset) {
            $dir = \dirname($dataset);
            yield basename($dir) => [$dir];
        }
    }

    /**
     * @dataProvider suites
     */
    public function testDatasetAndRubricsParseAndLineUp(string $dir): void
    {
        try {
            $dataset = Yaml::parseFile($dir.'/dataset.yaml');
            $rubrics = Yaml::parseFile($dir.'/rubrics.yaml');
        } catch (ParseException $e) {
            self::fail('invalid YAML in '.basename($dir).': '.$e->getMessage());
        }

        self::assertIsArray($dataset['questions'] ?? null, 'dataset has a questions list');
        self::assertIsArray($rubrics['rubrics'] ?? null, 'rubrics has a rubrics list');

        // Every rubric referenced by a question must exist, so a run never dangles.
        $rubricIds = array_map(fn (array $r) => $r['id'].'@'.$r['version'], $rubrics['rubrics']);
        foreach ($dataset['questions'] as $q) {
            self::assertArrayHasKey('id', $q);
            self::assertArrayHasKey('rubric_ref', $q);
            $ref = preg_replace('~^rubric/~', '', (string) $q['rubric_ref']);
            self::assertContains($ref, $rubricIds, "question {$q['id']} references a defined rubric");
        }

        // Every check declares a kind the runner understands.
        foreach ($rubrics['rubrics'] as $rubric) {
            self::assertNotEmpty($rubric['checks'] ?? [], "rubric {$rubric['id']} has checks");
            foreach ($rubric['checks'] as $check) {
                self::assertContains($check['kind'], ['must_contain_any', 'must_not_contain'], "rubric {$rubric['id']} uses a known check kind");
                self::assertNotEmpty($check['values'] ?? [], "rubric {$rubric['id']} check has values");
            }
        }
    }
}
