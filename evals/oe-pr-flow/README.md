# Evals: oe-pr-flow

Tests for the `skills/oe-pr-flow/SKILL.md` skill file.

The eval runner ships in the upstream package under `vendor/`, so every
command is run from this project's root and must pass `--root .` so the
runner evaluates this repo's `skills/` and `evals/` (not the vendored copy).

## Static assertions (no API calls, CI-safe)

```bash
composer evals
# or, equivalently:
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --static --skill oe-pr-flow --root .
```

Twenty-two checks (S01-S22): the skill file exists, its frontmatter follows
the Agent Skills spec, and the entry stays under 250 lines. It reads the
default branch from the remote and warns against the local `origin/HEAD`,
shows the commit line, and points to the commit rule
(`base-conventions.yml`), the PR template and the CI workflow
(`drupal-ci.yml`) instead of copying them. It requires Evidence and Merge
danger in the description, names the whole core matrix, lists the files CI
rewrites, the MySQL start-up race and `@group batchN`, defers
`git.drupalcode.org` to `drupal-gitlab`, and states the never-merge rule.

This is the path CI runs on every pull request.

## Behavioural cases (requires a provider CLI)

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --behavioral --skill oe-pr-flow --provider kiro --root .
```

Omitting `--root .` makes the runner look in the vendored package, which has
no `dataset.yaml`, and it prints "No dataset.yaml found, skipping". Point it
at this repo with `--root .`.

The kiro provider runs Kiro CLI v3 answer-only with an empty throwaway
`KIRO_HOME`, so no steering, skills or agents from your own `~/.kiro` leak
in. The runner puts the skill text in the prompt. The prompts never name the
skill, so a pass means the skill's content changed the answer. Swap the
provider with `--provider` (`claude`, `codex`, `gemini`, `mistral`); the
chosen CLI must be installed and authenticated. Ten cases:

- B01 - commit message: ticket key, colon, capital, full stop, no trailer.
- B02 - base branch: ask the remote, do not trust the local `origin/HEAD`.
- B03 - reject a fix that works on Drupal 10.6 only.
- B04 - stage only the real change, not the files CI rewrites.
- B05 - rerun a "Connection refused" in the 10.6 job instead of debugging.
- B06 - a `git.drupalcode.org` remote defers to `drupal-gitlab`.
- B07 - fill the repository's PR template, with Evidence and Merge danger
  inside Description, and keep its headings.
- B08 - unclear host: ask one question instead of guessing.
- B09 - never merge a pull request.
- B10 - "commit" is not "push".

Measured with the skill body replaced by a stub (same model, kiro
provider): B01, B02, B06 and B07 fail without the skill, so they show that
its content changes the answer. B03, B04, B05 and B08-B10 pass without it,
so they are regression guards for rules the model already follows by
default. They stay because the ticket requires them and a later skill edit
could break them.

These need a live model, so they are run on demand, not in CI. The cases
are answer-only: each prompt gives the context it needs and asks for a
fixed final line, which the rubrics score. B06 checks that the agent names
`drupal-gitlab`, not that it follows that skill's content, because the
upstream skill is not loaded in the run.

## Trace logging

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --behavioral --skill oe-pr-flow --provider kiro --root . \
  --output-dir ./traces
```

Saves one JSON file per case (full prompt, raw response, per-check results)
under `./traces/oe-pr-flow/`, for offline review of why a case passed or
failed. Delete `./traces` afterwards; it is not part of the change.
