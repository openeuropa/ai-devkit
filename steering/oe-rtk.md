---
inclusion: always
---

# RTK: token-efficient commands

Prefix every shell command with `rtk` so its output is filtered before you
read it; unknown commands pass through unchanged. Chains too:
`rtk git add -A && rtk git commit -m "msg"`. When a command fails and you
need the full output, run `rtk proxy <command>` once.

Common targets: `rtk vendor/bin/run toolkit:test-phpunit`,
`rtk vendor/bin/run toolkit:test-behat`, `rtk vendor/bin/run toolkit:test-phpcs`,
`rtk vendor/bin/phpstan analyse`, `rtk vendor/bin/grumphp run`.

#[[file:.kiro/steering/project-rtk.md]]
