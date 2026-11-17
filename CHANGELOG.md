# Changelog

## Unreleased

- Docs: README "Using it" now states v3 is the default from Kiro CLI 3.0 and
  keeps the opt-in steps only for older 2.x CLIs. It also documents the
  `oe-automated-testing` skill and the reviewer's test review and secret-read
  denies, and notes upstream's empty-`require` bug and the evals' CLI version.
- Skeleton: Composer plugin that requires `drupal/ai_best_practices`, points
  its skill sync at `.kiro/skills`, and places four steering files, the
  `oe-guardrail` PreToolUse hook and the `oe-reviewer` agent; seed skill
  `oe-project-wiki`; CI with an end-to-end smoke install.
- Skill `oe-pr-flow` (OEL-5138): base branch, branch name, commit line, pull
  request description, the every-supported-Drupal-version rule and CI
  reading for OpenEuropa components on GitHub; defers `git.drupalcode.org`
  to upstream `drupal-gitlab`. Evals in `evals/oe-pr-flow/`.
- Guardrail: pushes to version branches (`6.x`) are blocked, and a push must
  name the remote and the branch (a bare `git push`, `HEAD`, `--all` and
  `--mirror` are blocked; `+branch` and `:branch` refspecs are caught).
- `oe-reviewer` loads `oe-pr-flow` and may run read-only `gh` commands.
- Steering tells the agent to load `oe-pr-flow` before a base-branch choice,
  a commit message, a pull request or a CI read.
