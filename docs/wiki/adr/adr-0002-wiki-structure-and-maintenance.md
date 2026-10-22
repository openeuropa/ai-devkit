# Wiki structure and maintenance

- Status: proposed
- Date: 2026-10-21
- Origin: OEL-5055

## Context

adr-0001 placed the knowledge base in-repo under `docs/wiki/`. This decision
covers the three remaining "how it works" questions: the canonical page
structure, the maintenance rule, and the issue-tracker linkage policy.

## Decision

### Canonical structure

```
docs/wiki/
  index.md            entry point; lists every page by area
  adr/                numbered architectural decision records
  decisions/          lighter feature / scoped decisions
  conventions/        directory and code conventions (reference)
  patterns/           the pattern catalogue (reference)
```

Two page shapes: **decision** (Context / Decision / Consequences with a
Status / Date / Origin header; optional "Options considered" section) and
**reference** (title, one-paragraph purpose, "researched against" line with
date and versions, tables, code-path links, optional Origin).

ADRs are numbered `adr-NNNN-<slug>.md`, never reused; a reversed decision
flips to "superseded by" and links to the replacement. Feature decisions are
named by slug, not numbered.

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
- **Promotion rule:** `local_docs/issues/<ID>/` notes are transient; only the
  durable conclusion is promoted into `docs/wiki/`, carrying the issue key as
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
