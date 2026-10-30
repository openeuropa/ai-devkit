# OpenEuropa AI DevKit

The OpenEuropa layer on top of
[`drupal/ai_best_practices`](https://www.drupal.org/project/ai_best_practices):
OpenEuropa skills, Kiro steering, a guardrail hook and a read-only reviewer
agent. The Drupal community package does the skill sync; this package adds
what is specific to OpenEuropa and Kiro.

## Requirements

- PHP 8.3+ and Composer 2.
- [Kiro CLI](https://kiro.dev/docs/cli/) v3 for the agent itself. Node is not
  needed.

## Install (Drupal project)

```bash
composer require --dev drupal/ai_best_practices:1.0.x-dev@dev openeuropa/ai-devkit
```

Then commit what the install produced. You add nothing by hand; these are
the files the two plugins wrote:

```bash
git add .kiro .aiignore AGENTS.md CLAUDE.md GEMINI.md ai_best_practices.yaml composer.json composer.lock
```

Once a project adds its own skills, commit `skills/` too.

Notes:

- **Both plugins must be allowed.** If `drupal/ai_best_practices` is not in
  `allow-plugins`, no skills are synced; if `openeuropa/ai-devkit` is not,
  the Kiro files are missing and skills land in `.agents/skills`. Run
  interactively, `composer require` asks about each plugin and adds them to
  `allow-plugins`. In non-interactive runs (CI, an agent) Composer stops with
  an error instead of asking, so run these first:

  ```bash
  composer config allow-plugins.drupal/ai_best_practices true
  composer config allow-plugins.openeuropa/ai-devkit true
  ```
- **Upstream has no stable release yet.** A project on
  `minimum-stability: stable` must require its dev version explicitly
  (`drupal/ai_best_practices:1.0.x-dev@dev`, as above); otherwise Composer
  cannot resolve the devkit.
- **Commit `.kiro/skills/`.** It is generated, but committing it gives every
  teammate and CI job the same skills without running Composer first, and
  shows skill changes in merge requests.

## What happens on install and update

1. This plugin creates `ai_best_practices.yaml` from upstream's template, with
   `skills.output_directory: .kiro/skills` (Kiro reads skills only from
   there), when the file does not exist yet. It never edits an existing file;
   if yours points elsewhere it prints a warning.
2. It places its Kiro files in `.kiro/` (see below). Files it manages are
   refreshed on every install; files you add next to them are not touched.
3. `drupal/ai_best_practices` then syncs skills from every Composer package
   (including this one) and from the project's `skills/` into `.kiro/skills/`,
   updates its region in `AGENTS.md`, adds `CLAUDE.md` / `GEMINI.md` when
   missing, and registers `composer drupal-ai` in `composer.json`.

Re-sync skills at any time with `composer drupal-ai skills-sync`; list them
with `composer drupal-ai skills-list`.

## What lands in a project

```
.kiro/skills/            synced by drupal/ai_best_practices (generated; do not edit)
.kiro/steering/oe-*.md   always-on rules: working style, guardrails, RTK, writing conventions
.kiro/steering/project-guardrails.md  created once, yours: this project's extra guardrails
.kiro/steering/project-rtk.md         created once, yours: this project's commands, prefixed with rtk
.kiro/oe-guardrail-patterns.txt       created once, yours: extra commands the guardrail blocks
.aiignore                             created once, yours: files the guardrail never lets a command read
.kiro/hooks/             oe-guardrails.json, the PreToolUse guardrail
.kiro/agents/            oe-reviewer.json + prompt, the read-only reviewer sub-agent
.kiro/.gitignore         created once, yours afterwards
ai_best_practices.yaml   upstream's settings file, created once pointing at .kiro/skills
AGENTS.md                upstream's managed region; everything outside the markers is yours
```

Managed by this devkit (refreshed on every install): `.kiro/steering/oe-*.md`,
`.kiro/hooks/oe-guardrails.json`, `.kiro/agents/oe-reviewer.*`. Do not edit
those; add your own files beside them instead.

When a devkit release changes a template, `composer update` rewrites the
managed files and `.kiro/skills/`, and the change shows up in the merge
request like any other diff. Files created once (marked "yours" above) are
never rewritten, so a project keeps its own version; compare it with the
devkit's `templates/` if you want a later example.

`project-guardrails.md` and `project-rtk.md` are where a project adds its own
guardrails and its [RTK](https://github.com/rtk-ai/rtk) command list.
`oe-guardrails.md` and `oe-rtk.md` reference them. The devkit creates both once and never overwrites them; commit
them. They are always-on, because Kiro CLI v3 does not expand the references
in steering files.

## Using it

- Use Kiro's v3 engine. From Kiro CLI 3.0 it is the default, so a current CLI
  runs the devkit's reviewer agent and guardrail hook out of the box; no setup
  is needed. On an older 2.x CLI the v2 engine runs by default and skips both
  without a message, so either upgrade to 3.0 or select v3 yourself:

  ```bash
  kiro-cli settings chat.agentEngine v3
  ```

  Or pass `--v3` each time, as in `kiro-cli chat --v3`.
- Start Kiro in the project root: `kiro-cli chat`. Kiro reads `.kiro/` only
  from the folder it starts in, so a session started in a subfolder gets none
  of the steering, guardrails or skills. The default agent loads `AGENTS.md`,
  the steering files and the skill names automatically.
- Run the reviewer with `kiro-cli chat --agent oe-reviewer` (or pick it with
  `/agent` in a session). It runs on `claude-sonnet-5`, a different model from
  the one that wrote the change, reads, runs `git diff` / phpcs / phpstan, and
  reports without editing. To review with another model, start it with
  `kiro-cli chat --agent oe-reviewer --model <id>`; your own default model
  does not replace the pin. The default agent can also hand it a review ("have
  oe-reviewer check this"); the reviewer keeps its own permissions when it runs
  that way. If your Kiro Enterprise model allow-list does not include
  `claude-sonnet-5`, Kiro falls back to the default model with a warning.
- Add project skills under `skills/<prefix>-<name>/SKILL.md` and run
  `composer drupal-ai skills-sync`. A project skill with the same name as an
  upstream or devkit skill replaces it.

## Configuration and switches

| Setting | Where | Effect |
|---|---|---|
| `skills.output_directory` | `ai_best_practices.yaml` | Must be `.kiro/skills` for Kiro |
| `skills.exclude_skills` | `ai_best_practices.yaml` | Drop a skill, e.g. `drupal/ai_best_practices:drupal-accessibility` |
| `OE_AI_DEVKIT_SKIP_SYNC=1` | environment | Skip this devkit's step (no Kiro files, no yaml) |
| `AI_BEST_PRACTICES_SKIP_PACKAGE_SYNC=1` | environment | Skip upstream's skill sync and `AGENTS.md` update |
| `AI_BEST_PRACTICES_SKIP_COMPOSER_MUTATION=1` | environment | Stop upstream adding the `drupal-ai` script to `composer.json` |
| `.kiro/oe-guardrail-patterns.txt` | project | Extra PCRE patterns the guardrail blocks, one per line. Created once with commented examples |
| `.aiignore` | project | Globs of files the guardrail never lets a shell command read. Created once with commented examples |

## Known limitations

- **The guardrail is a tripwire, not a sandbox.** It blocks commands that
  name a credential file or an `.aiignore` path in any argument (whichever
  program reads it), network recon tools, curl and wget to hosts outside the
  allowlist, and pushes to protected branches. A path assembled at run time,
  or another program that opens a connection, gets past it. Network egress
  is enforced by the DevOps image's firewall, not by the hook. It checks
  shell commands only, so Kiro's own file-read tool is not covered yet.
- **The guardrail fails open.** Kiro blocks a command only when the hook
  exits with code 2. When `php` or `vendor/` is missing, or the guardrail
  crashes, Kiro runs the command without a message.

- **Symlinked installs hide the devkit's skills.** Upstream's skill scanner
  skips symlinked directories. A Composer `path` repository symlinks by
  default, so when you install the devkit from a local checkout (for example
  while developing it), disable that:

  ```bash
  composer config repositories.devkit '{"type": "path", "url": "../ai-devkit", "options": {"symlink": false}}'
  ```

  Installs from Packagist or a VCS repository are not affected.
- **Default `bin-dir` assumed.** The guardrail hook runs
  `vendor/bin/oe-guardrail`. With a custom `bin-dir`, copy
  `.kiro/hooks/oe-guardrails.json` to a project hook with the right path.
- **Upstream's `AGENTS.md` text** says skills are installed into
  `.agents/skills/`; with this devkit they are in `.kiro/skills/`. The text
  comes from upstream's template and cannot be changed here.
- **Existing `ai_best_practices.yaml`.** If the project already had one
  before installing the devkit, set `skills.output_directory: .kiro/skills`
  yourself and run `composer drupal-ai skills-sync`; the plugin warns until
  you do.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| No `oe-*` skill in `.kiro/skills/` | Devkit installed through a symlinked `path` repository; see "Known limitations" |
| Skills in `.agents/skills/` instead | `ai_best_practices.yaml` points elsewhere, or the devkit plugin is not allowed; fix and run `composer drupal-ai skills-sync` |
| No skills at all | `drupal/ai_best_practices` not in `allow-plugins`, or `AI_BEST_PRACTICES_SKIP_PACKAGE_SYNC` is set |
| `composer require` cannot resolve | Stable-only project without `drupal/ai_best_practices:1.0.x-dev@dev` |
| Sync aborts with a YAML error | `ai_best_practices.yaml` is malformed; upstream refuses to fall back to defaults. Fix the file |

## Uninstall

`composer remove openeuropa/ai-devkit` removes the package only. The files it
placed in `.kiro/` and `ai_best_practices.yaml` are project-owned and stay;
delete them yourself if you no longer want them.

## Developing this package

```bash
composer install            # upstream's plugin is disabled in this repository on purpose
composer test               # PHPUnit, including the 200-line steering budget
composer cs-fix             # php-cs-fixer (@Symfony), the same style tool and rules as upstream
composer phpstan            # PHPStan, level 8
vendor/bin/agent-skills-validator skills
scripts/ci-smoke-install.sh # end-to-end install into a throwaway project; needs network access to Packagist
composer evals              # static evals for this repository's skills, with upstream's runner
```

Behavioral evals run against Kiro CLI 2.31 or later, agent engine v3 (log in with
`kiro-cli login` first). Each call runs with an empty `KIRO_HOME`, so your own
`~/.kiro` steering and skills stay out of the results:

```bash
python3 vendor/drupal/ai_best_practices/evals/run-evals.py --behavioral --root . --provider kiro
```

**Upstream pin.** Upstream has no tagged release yet, so `composer.json` pins
`drupal/ai_best_practices` to the 1.0.x commit that added the Kiro provider
([#3589004](https://git.drupalcode.org/project/ai_best_practices/-/work_items/3589004))
as `1.0.x-dev#<commit>`. Composer honours a commit reference only in the root
package, so the pin holds in this repository and its CI, while projects that
install the devkit still resolve `1.0.x-dev` themselves. Move the pin
deliberately after reviewing upstream changes, or drop it once upstream tags a
release.

Every skill ships `evals/<name>/`; a skill change needs an eval change.
Steering stays under 200 lines in total. Licence: EUPL-1.2.
