# Evals: oe-project-wiki

Tests for the `skills/oe-project-wiki/SKILL.md` skill file.

The eval runner ships in the upstream package under `vendor/`, so every
command is run from this project's root and must pass `--root .` so the
runner evaluates this repo's `skills/` and `evals/` (not the vendored copy).

## Static assertions (no API calls, CI-safe)

```bash
composer evals
# or, equivalently:
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --static --skill oe-project-wiki --root .
```

Ten checks (S01-S10): the skill file exists, its frontmatter follows the
Agent Skills spec, the entry stays under 120 lines, and it names the wiki
index, the four page types (`adr/`, `decisions/`, `conventions/`,
`patterns/`), the `Origin:` provenance rule, the `templates/wiki/` skeleton,
and the "never write it unasked" rule.

This is the path CI runs on every pull request.

## Behavioural cases (requires a provider CLI)

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --behavioral --skill oe-project-wiki --provider codex --root .
```

Omitting `--root .` makes the runner look in the vendored package, which has
no `dataset.yaml`, and it prints "No dataset.yaml found, skipping". Point it
at this repo with `--root .`.

Swap the provider with `--provider` (`claude`, `codex`, `gemini`, `mistral`);
the chosen CLI must be installed and authenticated. Three cases:

- B01 - wiki-first reading: read `docs/wiki/index.md` first, then only the
  pages relevant to the area being touched.
- B02 - propose a durable page with an `Origin:` provenance line, without
  writing it unasked.
- B03 - refuse to store transient notes (progress, ticket status) in the
  wiki.

These need a live model, so they are run on demand, not in CI.

## Trace logging

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py \
  --behavioral --skill oe-project-wiki --provider codex --root . \
  --output-dir ./traces
```

Saves one JSON file per case (full prompt, raw response, per-check results)
under `./traces/oe-project-wiki/`, for offline review of why a case passed or
failed.
