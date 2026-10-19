# ExistingSite tests on a site

ExistingSite tests come from `weitzman/drupal-test-traits` (DTT). They run
against the installed site and its database, so every test cleans up what it
creates. Copy the patterns of the tests next to the one you write.

## Classes and directories

| Need | Directory and namespace | Base class |
|---|---|---|
| Forms, pages, saved values | `tests/src/ExistingSite`, `modules/<m>/tests/src/ExistingSite` | `weitzman\DrupalTestTraits\ExistingSiteBase` |
| AJAX, modals, entity browsers | `.../ExistingSiteJavascript` | `weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase` |

Keep JavaScript tests out of `ExistingSite/`. The suite that runs that
directory usually has no browser, so the test fails in CI with a connection
error to the Selenium host. Some modules have their own abstract base class
on top of these. Extend it when the module has one.

## Shared traits

Sites keep shared test helpers as traits, usually in `tests/Traits/`:
logging in with roles, cleaning up entities, backing up config, creating
content and media, setting widget values. Look there before writing a
helper, and put a new one there when more than one class needs it.

## Rules

- **Clean up everything.** DTT deletes the entities you create through its
  API (`createNode()`, or `markEntityForCleanup()` for any other entity).
  Entities the UI creates are not tracked. Find and delete them in
  `tearDown()`, or use the project's cleanup trait. Back up config you
  change in `setUp()` and restore it in `tearDown()`.
- **Forced login.** When the site forces login (for example through EU
  Login), turn it off in `setUp()` and back on in `tearDown()`, with the
  helper the other tests use.
- **Time.** Freeze time instead of depending on the clock. When the project
  has `drupal/datetime_testing`, use `\Drupal::time()->freezeTime()` and
  `->setTime($timestamp)`, and `->resetTime()` in `tearDown()`.
- **Two processes.** The test and the web server do not share memory. Pass
  data between them through state, not static variables.
- **Batch groups.** When the project splits its tests into CI batches, every
  class needs a `@group batchN` that the CI config lists, Kernel and Unit
  tests included when CI batches them too. Pick the batch of the
  neighbouring tests and use the same syntax they do. A group CI does not
  list means the test never runs, or the run fails if the project checks
  for it.

## Entity browsers

Test each entity browser through its modal once. Everywhere else, set the
field value directly. The stored value is space-separated `entity_type:id`,
for example `node:12 node:34`. In the modal test, switch back to the main
document with `switchToIFrame()` before waiting for the preview. Waiting
inside the closing iframe fails with "Unable to complete AJAX request".

## Running locally

```bash
docker compose exec web ./vendor/bin/phpunit --testsuite <suite> --filter <TestClass> > tests/zz_phpunit.log
```

- The suite names are in `phpunit.xml.dist`.
- After editing PHP, run `docker compose restart web`. `drush cr` does not
  clear the web server's opcache.
- Mock modules must be enabled first. On a fresh site, enable them the way
  CI's install step does.
- The full suite fails locally for environment reasons (missing service
  credentials, leftover data). Do not try to make it green. Run the
  failing classes with and without your change and compare the two
  failure lists. Only the difference is yours.
