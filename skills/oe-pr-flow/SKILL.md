---
name: oe-pr-flow
description: Use before choosing a base branch, naming a branch, writing a commit message, opening a pull request, or reading a failed CI run in an OpenEuropa component (openeuropa/oe_*) on GitHub. Teaches the base branch, branch name, commit line, request description, the every-supported-Drupal-version rule, which CI failures to rerun and which CI-made changes never get committed. Defers to drupal-gitlab for git.drupalcode.org.
---

# Pull request and CI flow

OpenEuropa components (`oe_theme`, `oe_content`, ...) share one flow for
branches, commits, pull requests and CI. Agents get it wrong in six ways: the
wrong base branch, a commit line the commit-message rule rejects, an empty
request description, a fix that holds on one Drupal version only, CI-made
file changes committed by accident, and a CI-only failure debugged as a real
bug. This skill is the procedure. The facts that change (template, commit
rule, CI matrix) live in the repository, so read them there and do not copy
them from here.

## Which rules apply

Work out the host from `git remote get-url origin`:

- `github.com/openeuropa/...`: this skill applies. Use `gh`.
- `git.drupalcode.org`, or the work is a Drupal.org issue: load the
  `drupal-gitlab` skill and follow it, including its commit format, issue
  forks and merge requests. Its rules differ from the ones below (for
  example Drupal commits take a type and issue number, and CI cannot be
  rerun through the API). Do not apply this skill's commit line or CI
  steps there.
- Anything else, or no remote: ask one question and stop. Do not guess.

OpenEuropa has no GitLab of its own, so there is no GitLab flow here.

Commands used below, all `gh`:

- Default branch: `git remote show origin`, or `gh repo view`.
- Open: `gh pr create --base B --head H`. View: `gh pr view`, `gh pr diff`.
- CI: `gh pr checks`, `gh run view RUN --log-failed`,
  `gh run rerun RUN --failed`.
- Description template: `.github/pull_request_template.md`.

Use the CLI, not `curl`. Run `--help` before using a flag you are unsure of.

## Base branch

Components keep version branches (`1.x` to `6.x`) and the default differs
per repository. Read the default from the remote:

```bash
git remote show origin        # the "HEAD branch:" line
gh repo view --json defaultBranchRef -q .defaultBranchRef.name
```

Never take it from the local `origin/HEAD` or `git symbolic-ref
refs/remotes/origin/HEAD`: it is set at clone time and goes stale. Example:
locally `oe_theme` says `4.x` while GitHub says `6.x`. If the user names the
base branch, use theirs. A bugfix for an older release targets that
release's branch, which only the user can say: ask when it is not clear.

## Branch name

The ticket key and nothing else: `OEL-5137`, `EWPP-7263`. No prefix, no slug.
Push with an explicit target, `git push origin OEL-5137`. A bare `git push`
or `HEAD` is blocked by the guardrail, and so is any push to a version
branch.

## Commit message

One line, no body, no trailers (no `Co-authored-by`, no "Generated with"):

```text
OEL-5137: Add the automated testing skill.
```

Ticket key, a colon and a space, a capital letter, a full stop at the end.
The rule is `git_commit_message` in
`vendor/openeuropa/code-review/dist/base-conventions.yml`; GrumPHP rejects a
commit that breaks it. Read the file when it exists. The matcher also
accepts the Drupal.org form `Issue #123: Summary.`; use it only when the
user gives an issue number, not a ticket key. If the file is missing, use
the form above and say the rule file was not found.

Wrong, and why:

```text
add testing skill                  no ticket key, no capital, no full stop
OEL-5137: add testing skill.       lower-case after the colon
OEL-5137: Add skill.\n\nBody...    a body and trailers are not allowed
```

## Pull request description

Read the repository's own template and fill every section: write the text,
or "n/a" with a reason. Never leave a placeholder (`[Insert ... here]`) or
an empty heading. OpenEuropa's GitHub template has:

- `## OPENEUROPA-[ticket]`: put the ticket number in the brackets and keep
  the prefix.
- `### Description`.
- `### Change log`: Added, Changed, Deprecated, Removed, Fixed, Security.
  Fill the lines that apply, write "n/a" on the rest.
- `### Commands`: the commands a reviewer runs to see the change. If there
  are none, say so.

The repository's template is the structure of the description. Never
replace it with another layout, and do not paste this skill's section names
over it. If the repository has no template, use the four sections above.

Inside Description, show the reviewer the following, short:

1. Evidence (required): before and after. The test that failed and now
   passes, or the command output. A screenshot only for a visual change.
2. Merge danger (required): whether the change is a one-way or a two-way
   door (can it be rolled back), and its blast radius in a few words, for
   example "every site using the theme". One or two lines.
3. Summary (only when the change is not trivial): the smallest view that
   makes the point. Pseudocode for logic, a call tree for flow, a file tree
   for a refactor, a diff for a small change, Mermaid for interaction. A
   one-line fix needs none.

If the template already has a place for one of these, put it there.

Use the domain words the repository already uses. Skip preambles.

Title: the ticket key and a short summary, as in the commit line.

## Every supported Drupal version

A change must work on the whole CI matrix, not only on the version in the
local environment. Read the matrix from the component's
`.github/workflows/ci.yml` and the workflow it calls
(`openeuropa/github-ci`, `drupal-ci.yml`). At the time of writing the
default is core 10.6, 11.3 and 11.4, but a component can override it.

- Never propose a fix that works on one version only: not a call that
  exists only in 11.x, not one that exists only in 10.6.
- When two versions need different code, say so, branch on the capability
  (`method_exists`, `class_exists`) rather than on a version number where
  possible, and state it in the description.
- When you cannot run the other versions, say which you did not test.

## Reading CI

Start from the failing job: `gh pr checks`, then the failed log. Before
changing code, tell a CI-only failure from a real one.

CI rewrites files in its own checkout so the matrix can run. These changes
never belong in a commit:

- `phpunit.xml.dist` and `behat.yml.dist` (the `w3c` setting on Drupal 10),
- `docker-compose.yml` (PHP and Selenium image tags),
- the `drupal/core`, `drupal/core-composer-scaffold` and `drupal/core-dev`
  constraints in `composer.json` and `composer.lock`.

If `git status` shows one of them changed and the user did not ask for it,
restore it before committing. Run `git status` before every commit.

Known CI-only failures:

- A "Connection refused" database error in the Drupal 10.6 job is a MySQL
  start-up race. Rerun the failed job and do not debug it. If the rerun
  fails with the same error, stop and report it; do not rerun in a loop.
- A batched component fails when a test class has no `@group batchN`.
  A component is batched when its `ci.yml` sets `run_phpunit_batches: true`
  (for example `oe_content`, `oe_list_pages`, `oe_media`, `oe_theme`,
  `oe_translation`). Add the group to every new test class there. Look at the
  neighbouring tests for the number to use.
- A failure that shows on one matrix version only is a compatibility
  problem: fix it for all versions (see above), do not skip that version.

Reproduce a real failure locally on the failing version before editing code.

## Outward actions

Commit, push, open a pull request or comment only when the user asks, and
only what they asked for: "commit" is not "push", and "push" is not "open a
pull request". Never merge, approve or close one. Before a commit or a
request, load `oe-qa-gate` and run its checks.

## Reviewing a request

When asked to review, check: each commit line against the commit rule; the
description against the repository's template, with no empty section or
placeholder; the base branch against the remote default; CI-made file
changes in the diff; and whether the change holds on the whole matrix.
