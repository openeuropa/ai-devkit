# oe-automated-testing evals

Static checks: the skill and its three references exist, the frontmatter
follows the Agent Skills spec, and SKILL.md states the core rules (load
`drupal-automated-testing` first, no new Behat features, ExistingSite on a
site, no negative tests, mock modules for external services). Run them with
`composer evals`.

Behavioural cases (`dataset.yaml`, `rubrics.yaml`):

- B01: a new field on an EWCMS content type gets an ExistingSite test with
  a batch group and cleanup, not Behat or BrowserTestBase.
- B02: a modal flow goes to ExistingSiteJavascript, not a new feature file.
- B03: an external HTTP call is handled by an existing mock module.
- B04: a regression test asserts the saved state, not that the old
  exception is gone.
- B05: a failing test is never skipped or excluded to get CI green.

Run them against Kiro with upstream's runner:

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py --behavioral --root . --provider kiro --model claude-opus-5 --skill oe-automated-testing
```
