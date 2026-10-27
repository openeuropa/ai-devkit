#!/usr/bin/env bash
# End-to-end check: install the devkit into a throwaway Drupal-style consumer and verify the result.
# Upstream (drupal/ai_best_practices) comes from Packagist; the devkit from this checkout.
set -euo pipefail
DEVKIT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
cd "$WORK" && git init -q . && git config user.email ci@example.org && git config user.name ci

# A project skill with an upstream name: project skills must win over Composer packages.
mkdir -p skills/drupal-gitlab
printf -- '---\nname: drupal-gitlab\ndescription: PROJECT copy.\n---\n\n# project\n' > skills/drupal-gitlab/SKILL.md

composer init -q --name smoke/consumer --stability stable --no-interaction
# Copy, not symlink: upstream's skill scanner skips symlinked directories (as a real install has none).
composer config repositories.devkit "{\"type\": \"path\", \"url\": \"$DEVKIT\", \"options\": {\"symlink\": false}}"
composer config allow-plugins.drupal/ai_best_practices true
composer config allow-plugins.openeuropa/ai-devkit true
# Upstream has no stable release yet, so a stable-only project names its dev version explicitly.
composer require --dev "drupal/ai_best_practices:1.0.x-dev@dev" "openeuropa/ai-devkit:*@dev" --no-interaction 2>&1 | grep -v "could not detect the root package"

echo "--- upstream skill sync targets .kiro/skills"
grep -q 'output_directory: .kiro/skills' ai_best_practices.yaml
test -f .kiro/skills/drupal-configuration/SKILL.md     # upstream skill
test -f .kiro/skills/oe-project-wiki/SKILL.md          # devkit skill, discovered as a Composer package
grep -q 'PROJECT copy' .kiro/skills/drupal-gitlab/SKILL.md   # project skill wins
test ! -d .agents/skills || [ -z "$(ls -A .agents/skills)" ]

echo "--- Kiro wiring and upstream's AGENTS.md"
for f in .kiro/steering/oe-working-style.md .kiro/steering/oe-guardrails.md .kiro/steering/oe-rtk.md .kiro/steering/oe-writing-conventions.md \
         .kiro/hooks/oe-guardrails.json .kiro/agents/oe-reviewer.json .kiro/agents/oe-reviewer.prompt.md .kiro/.gitignore \
         .kiro/steering/project-guardrails.md .kiro/steering/project-rtk.md .kiro/oe-guardrail-patterns.txt .aiignore AGENTS.md; do
  test -f "$f" || { echo "missing $f"; exit 1; }
done
grep -q 'ai-best-practices:start' AGENTS.md
echo '{"hook_event_name":"PreToolUse","tool_name":"execute_bash","tool_input":{"command":"cat .env"}}' | vendor/bin/oe-guardrail 2>/dev/null && { echo "guardrail did not block"; exit 1; } || true

echo "--- a second install changes nothing"
echo "/vendor/" > .gitignore
git add -A && git commit -qm snapshot
composer install --no-interaction >/dev/null 2>&1
if [ -n "$(git status --porcelain)" ]; then git status --porcelain; echo "second install was not idempotent"; exit 1; fi

echo "smoke-install passed"
