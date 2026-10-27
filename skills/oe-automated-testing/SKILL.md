---
name: oe-automated-testing
description: Use whenever the user asks for automated tests, test coverage or a regression test, or to write, fix, run or review PHPUnit, ExistingSite, Kernel, Functional, FunctionalJavascript, Unit or Behat tests in an OpenEuropa project (EWCMS or an oe_* component). Load it before writing any test. Builds on drupal-automated-testing with the OpenEuropa conventions - ExistingSite tests instead of new Behat features, mock modules for external services, one positive test per behaviour, batch groups, and how to run the suites in the project's Docker stack.
---

# Automated tests in OpenEuropa projects

Load `drupal-automated-testing` first. It covers the test types, namespaces,
Kernel setup, `waitForElement` and the dual-container trap. This skill adds
the OpenEuropa conventions on top. Where the two disagree, this skill wins:

- Tests run in the project's Docker Compose stack, not DDEV (see "Running").
- Cover the error paths the change itself handles, not every likely one.
- On a site, the default test type is ExistingSite, not Functional.

Upstream's `#[RunTestsInSeparateProcesses]` rule applies to every new
component Kernel, Functional and FunctionalJavascript class, even when the
tests next to it lack the attribute. It does not apply to ExistingSite
classes.

## Pick where the test goes

Check `phpunit.xml.dist` first. It tells you which kind of project this is.

| Project | Suites | New coverage goes in |
|---|---|---|
| Site (EWCMS) | `existing-site-no-javascript`, `existing-site-javascript`, `kernel`, `unit` | `ExistingSite/`, or `ExistingSiteJavascript/` when the flow needs JavaScript |
| Component (`oe_*`) | one suite over `modules/**/tests` and `tests` | `Kernel/` for services, hooks and entities, `Functional/` for pages and forms, `FunctionalJavascript/` for AJAX and modals |

- A site's ExistingSite tests run against the installed site. Read
  `references/existing-site.md` before writing one.
- A component builds its own site per test. Read `references/components.md`
  for its base classes and fixtures.
- Unit tests only for logic with no container, with collaborators from
  `createMock()`. They are rare in OpenEuropa code.

## Behat

Never create a new `.feature` file. New coverage goes into PHPUnit.

- When a change alters a flow an existing scenario covers, update the steps
  of that scenario.
- Context classes (`tests/Behat/*Context.php`) are helpers and can change.

EWCMS has no Behat left. Components such as `oe_paragraphs`, `oe_webtools`
and `oe_multilingual` still have features.

## What to test

- **One test per behaviour, at one level.** Pick the level that proves what
  the user sees and stop there. Do not assert the same thing again in a
  Kernel or Unit test.
- **Reuse what exists.** Before adding a fixture, content type, config or
  helper, try the existing ones. Add a new one only when the existing ones
  cannot reproduce the case, and say why.
- **Only the code the change touched.** Do not add coverage for code next to
  it.
- **Assert each fact once.** No status check next to a content check that
  already implies it.
- **Assert the result positively.** Check the state the change produces:
  the saved value, the rendered text, the redirect. A status code or "no
  exception was thrown" alone proves nothing. Assert the value itself.
- **No negative tests.** Do not assert that something is absent, that an
  old bug no longer happens, or that an old exception is still thrown.
  The exception is a denial that is itself the feature, such as access
  denied for a role. It gets one assertion per code path.
- **Test a third-party widget through the UI once.** One end-to-end test
  per widget (an entity browser, a media library) proves it works. Other
  tests set its value directly. EWCMS has
  `EntityBrowserTrait::selectInEntityBrowser()` for this.
- **Names and docblocks describe the behaviour.** "An unknown template is
  rejected as an invalid request", not "returns 400 instead of 500". No
  comments that narrate the change.

## Mock external services

Tests never call a real external service. OpenEuropa mocks them in mock
modules that CI enables before the tests run.

1. Find the existing mock: search for `*_mock` modules in the project and in
   `vendor/openeuropa/`. Most services already have one (EU Login, Europa
   Search, Newsroom, Webtools, ePoetry, eTranslation, AV Portal, CircaBC).
2. Make sure it is enabled the way CI enables it. Sites list mock modules in
   both `drush en` lines of `.opts.yml` and in `runner.yml.dist` under
   `drupal.post_install`.
   Components enable them from the test's `$modules`.
3. Steer the mock through state when it supports that, for example
   `\Drupal::state()->set('ewcms_content_topics_mock_enabled', TRUE)`.
4. Only when no mock exists, write one in the style of the nearest existing
   mock. `references/mocking.md` lists the patterns.

## Running

- Site: `docker-compose exec web ./vendor/bin/phpunit --testsuite existing-site-no-javascript --filter <TestClass>`
- Component: `docker-compose exec web ./vendor/bin/phpunit <path/to/Test.php>`
- Behat, in components that have it: `docker-compose exec web ./vendor/bin/behat --strict <path/to.feature>`

Run the test you wrote first, then the classes around it. Write the output
to a file (`> tests/zz_phpunit.log`) instead of piping it through `tail`,
and delete the file before handover. JavaScript tests need the `selenium`
service. When they hang, check it before you suspect the code.

## Never

- Change an assertion, skip a test or add `@group` exclusions to make a
  failing test pass. Fix the code, or report the failure.
- Add `phpcs:ignore` or `@phpstan-ignore` in test code.
- Commit `phpunit.xml`, `behat.yml` or other files generated from a `.dist`.

## Before handover

Count the tests and assertions you added per behaviour and cut to one.
Remove negative assertions that are not the feature. Check that every new
test in a batched project has its `@group batchN` (see the references). On
EWCMS that includes Kernel and Unit tests, which need `@group batch1`.
