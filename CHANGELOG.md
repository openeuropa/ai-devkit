# Changelog

## Unreleased

- Docs: README "Using it" now states v3 is the default from Kiro CLI 3.0 and
  keeps the opt-in steps only for older 2.x CLIs.
- Skeleton: Composer plugin that requires `drupal/ai_best_practices`, points
  its skill sync at `.kiro/skills`, and places four steering files, the
  `oe-guardrail` PreToolUse hook and the `oe-reviewer` agent; seed skill
  `oe-project-wiki`; CI with an end-to-end smoke install.
