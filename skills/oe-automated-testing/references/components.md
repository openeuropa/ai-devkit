# Tests in oe_* components

A component builds a fresh site for each Kernel, Functional and
FunctionalJavascript test. There are no ExistingSite tests, except in
`oe_ai_assistant`, which has its own `existing-site` suite.

## Base classes

Extend the component's own base class when it has one. It enables the mock
modules and the setup the other tests rely on.

| Component | Base classes |
|---|---|
| `oe_theme` | `AbstractKernelTestBase` (enables `oe_theme_webtools_mock`), `ContentRenderTestBase` |
| `oe_content` | `ContentTestBase`, and `SparqlKernelTestBase` from `sparql_entity_storage` |
| `oe_media` | `MediaTestBase` (Kernel), `MediaFeatureTestBase` (Functional and FunctionalJavascript) |
| `oe_translation` | `TranslationKernelTestBase` |
| `oe_list_pages` | `ListsSourceTestBase` |

Otherwise use the core bases: `KernelTestBase`, `EntityKernelTestBase`,
`MediaKernelTestBase`, `BrowserTestBase`, `WebDriverTestBase`.

## Layout

- Test modules: `tests/modules/<name>_test`.
- Mock modules: often a submodule, `modules/<sub>/modules/<name>_mock`.
- Fixtures: `tests/fixtures`, or inside the mock module (`responses/`,
  `fixtures/`).

## Faster Functional tests

`openeuropa/oe_testing_utils` provides `CachedDatabaseInstallTrait`. Set
`$this->cacheDbInstall = TRUE;` before `parent::setUp()` and the installed
site is cached as a database dump (MySQL only). Use it when the component's
other Functional tests do. To force a fresh install, delete
`sites/default/files/functional-test-dumps`.

## Batch groups

`oe_content`, `oe_list_pages`, `oe_media`, `oe_theme` and `oe_translation`
split their suite into batches, configured in `runner.yml.dist` under
`toolkit.test.phpunit.batches`. Every test class there needs a
`@group batchN` that is listed in that file, or CI fails. Pick the batch of
the neighbouring tests.

## Running

```bash
docker-compose exec web ./vendor/bin/phpunit tests/src/Kernel/SomeTest.php
docker-compose exec web ./vendor/bin/run toolkit:test-phpunit-batches   # batched components, as CI runs them
```

CI (`openeuropa/github-ci`) runs the suite on Drupal 10.6, 11.3 and 11.4.
To run on Drupal 10 it rewrites `phpunit.xml.dist` and the `drupal/core`
constraint. Those changes are CI's own and never belong in a commit.

## Style

Follow the file you are in: `@group` annotations and `assertEquals` in most
components, `#[Group]` attributes and `assertSame` in `oe_ai_assistant`.
