# ExistingSite tests on a site

ExistingSite tests come from `weitzman/drupal-test-traits` (DTT). They run
against the installed site and its database, so every test cleans up what it
creates. EWCMS is the reference: copy the patterns of the tests next to the
one you write.

## Classes and directories

| Need | Directory and namespace | Base class |
|---|---|---|
| Forms, pages, saved values | `tests/src/ExistingSite`, `modules/<m>/tests/src/ExistingSite` | `weitzman\DrupalTestTraits\ExistingSiteBase` |
| AJAX, modals, entity browsers | `.../ExistingSiteJavascript` | `weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase` |

A JavaScript test placed in `ExistingSite/` fails in CI with
`Could not resolve host: selenium`, because that suite runs without the
Selenium container. Some modules have an abstract base on top of these, such
as `ListPagesTestBase`. Extend it when the module has one.

## Shared traits

EWCMS keeps them in `tests/Traits/`. Use them instead of writing a helper.

| Trait | Use |
|---|---|
| `AuthenticationTrait` | `loginWithRoles(['oe_author'])`, `disableForcedLogin()` / `enableForcedLogin()` |
| `ExistingSiteCleanupTrait` | `cleanupEntityType('node')` in `setUp()`, `deleteExtraEntities()` in `tearDown()` |
| `ExistingSiteConfigBackupTrait` | `backupSimpleConfig()` / `backupConfigEntity()`, then `restoreConfiguration()` in `tearDown()` |
| `ContentTypeTestTrait`, `ContentTestTrait`, `MediaTestTrait` | create content and media with the required fields |
| `EntityBrowserTrait` | `selectInEntityBrowser()` sets an entity browser value without the modal |

The usual shape:

```php
/**
 * @group batch2
 */
class AliasesTest extends ExistingSiteBase {

  use AuthenticationTrait;
  use ExistingSiteCleanupTrait;

  protected function setUp(): void {
    parent::setUp();
    $this->cleanupEntityType('node');
    $this->disableForcedLogin();
  }

  protected function tearDown(): void {
    $this->enableForcedLogin();
    $this->deleteExtraEntities();
    parent::tearDown();
  }

}
```

## Rules

- **Clean up everything.** Entities you create through the API go through
  `markEntityForCleanup()` (DTT's `createNode()` does it for you). Entities
  the UI creates are caught by `cleanupEntityType()` and
  `deleteExtraEntities()`. Config you change is backed up and restored.
- **Forced login.** The profile forces CAS login. Call
  `disableForcedLogin()` in `setUp()` and `enableForcedLogin()` in
  `tearDown()`.
- **Time.** Freeze it through `datetime_testing`:
  `\Drupal::time()->freezeTime()`, `->setTime($timestamp)`, and
  `->resetTime()` in `tearDown()`.
- **Two processes.** The test and the web server do not share memory. Pass
  data between them through state, not static variables.
- **Groups.** Use docblock annotations (`@group batchN`), as the existing
  tests do. Every ExistingSite class needs one. The batch must be listed in
  `.opts.yml`, under `functional_matrix` for `ExistingSite` and
  `functional_matrix_js` for `ExistingSiteJavascript`. Pick a batch the
  neighbouring tests use. `tests/EnsurePHPUnitBatchingTestExtension.php`
  fails the run when the group is missing or unknown.
- **Kernel and Unit groups.** Kernel and Unit tests on the site need
  `@group batch1`, the batch listed under `kernel_unit_matrix`. The
  extension does not check them, so without the group the test never runs
  in CI.

## Entity browsers

Test each entity browser through its modal once. Everywhere else, call
`selectInEntityBrowser($name_fragment, 'node', [12, 34])`. The stored value
is space-separated `entity_type:id`. In the modal test, switch back to the
main document with `switchToIFrame()` before waiting for the preview.
Waiting inside the closing iframe fails with "Unable to complete AJAX
request".

## Running locally

```bash
docker-compose exec web ./vendor/bin/phpunit --testsuite existing-site-no-javascript --filter AliasesTest > tests/zz_phpunit.log
```

- After editing PHP, run `docker-compose restart web`. `drush cr` does not
  clear the web server's opcache.
- Mock modules and `datetime_testing` must be enabled first. Run the
  `drush en` line from `.opts.yml` (`script_site_install`) when a fresh
  site lacks them.
- The full suite fails locally for environment reasons (missing service
  credentials, leftover data). Do not try to make it green. Run the
  failing classes with and without your change and compare the two
  failure lists. Only the difference is yours.
