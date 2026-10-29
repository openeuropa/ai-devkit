# Wiki structure and maintenance

- Status: proposed
- Date: 2026-10-21
- Type: adr
- Origin: OEL-5055

## Context

adr-0001 placed the knowledge base in-repo under `docs/wiki/`. This decision
covers the three remaining "how it works" questions: the canonical page
structure, the maintenance rule, and the issue-tracker linkage policy.

## Decision

### Canonical structure

Pages are grouped by **area** (a module, subsystem or feature), not by type.
Each page names its type in a `Type:` header.

```
docs/wiki/
  index.md            entry point; lists every page by area
  <area>/             one folder per module / subsystem / feature
    adr-NNNN-<slug>.md   Type: adr        numbered architectural decision
    <slug>.md            Type: decision   lighter scoped decision
    <slug>.md            Type: convention directory / code convention (reference)
    <slug>.md            Type: pattern    a pattern in the catalogue (reference)
    <slug>.md            Type: pitfall    a trap to avoid (reference)
```

Five page types, declared in each page's `Type:` header, in two shapes:
**decision** (`adr`, `decision`) and **reference** (`convention`, `pattern`,
`pitfall`). The decision shape is Context / Decision / Consequences with a
Status / Date / Type / Origin header (optional "Options considered"); the
reference shape is title, one-paragraph purpose, a "researched against" line
with date and versions, tables, code-path links, optional Origin, plus the
`Type:` header.

Grouping by area (not by type) matches how the agent searches: it opens the
pages for the area it is about to touch. The type lives in the page header so a
single area folder can hold every kind of page about that area.

ADRs are numbered `adr-NNNN-<slug>.md`, never reused across the wiki; a
reversed decision flips to "superseded by" and links to the replacement.
Decisions, conventions, patterns and pitfalls are named by slug, not numbered.

`docs/wiki/index.md` is the single entry point: read it first, then only the
pages it lists for the area being touched (progressive disclosure).

### Maintenance rule

Layered, with the human reviewer as the gate, not a hard CI blocker:

1. **Agent proposes (soft).** The `oe-project-wiki` skill proposes a page at
   the end of a task; never writes unasked.
2. **Reviewer decides (gate).** The PR review includes one checklist item:
   "Architectural change? If so, the wiki is updated in this PR." The
   reviewer confirms accuracy, not merely presence.
3. **Drift rule (always).** When code and a wiki page disagree, trust the
   code and note the drift.

No hard CI check: it would enforce "a file was touched" rather than accuracy,
is trivially gamed, and trains contributors to bypass it.

### Issue-tracker linkage

- **Wiki -> Jira (out):** `Origin: <ISSUE-KEY>` as a provenance footnote
  only - the page must stand on its own because issues get archived and agents
  cannot always reach Jira.
- **Jira/PR -> wiki (in):** the PR that makes an architectural change also
  updates `docs/wiki/`. Traceability flows issue -> PR -> wiki and lives in
  git.
- **Promotion rule:** an issue's working notes are transient; only the durable
  conclusion is promoted into `docs/wiki/`, carrying the issue key as
  provenance. A finding is promoted when it would save the next person an
  investigation.

## Consequences

- Easier: a fixed taxonomy and two shapes keep projects consistent; the index
  gives the agent a cheap entry point; `Origin:` carries provenance with no
  extra mechanism; the reviewer already verifies correctness.
- Harder: promotion is a judgement call; reviewers must honour the checklist;
  a lax review lets the wiki drift (accepted trade-off).
- Deferred: if drift proves real, a non-gaming automated nudge (a reminder
  comment, not a CI failure) can be added later.
