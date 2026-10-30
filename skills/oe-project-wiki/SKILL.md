---
name: oe-project-wiki
description: Use at the start of any ticket or investigation in an OpenEuropa repository, and again when an investigation produced something durable. Tells the agent to read the project's docs/wiki/index.md before working, how to decide whether a finding belongs in the wiki, and how to propose a page (or a kenkeep note when .ai/kenkeep/ exists) without writing it unasked.
---

# Project wiki

OpenEuropa projects keep durable knowledge in `docs/wiki/`: architecture,
conventions, past investigations, pitfalls. It is the project's memory
between agent sessions.

The wiki groups pages by **area** (a module, subsystem or feature) under
`docs/wiki/`, entered through `index.md`. Each page names its **type** in a
`Type:` header: `adr` (numbered architectural decision), `decision` (lighter
scoped decision), `convention` (where things live, how they are named),
`pattern` (reusable solution) or `pitfall` (a trap to avoid). ADRs and
decisions use the decision shape (Context / Decision / Consequences with a
Status / Date / Type / Origin header); conventions, patterns and pitfalls use
the reference shape. The starter skeleton (an `index.md` plus one example per
type) ships with this skill under `references/`; copy from there when a
project needs a wiki.

### ADR and decision status

The `Status:` header on a decision-shape page takes one of:

- `accepted` — the default; a merged decision in effect. A new page starts
  here, because the merge is the acceptance (see adr-0003).
- `superseded by adr-NNNN-<slug>` — replaced by a specific later ADR. The file
  stays; the status points to its successor.
- `deprecated` — no longer applies, with no replacement (nothing superseded
  it; the thing it decided about went away).

There is no `proposed` state: a decision under review is tracked by its open
merge request, not by a header value that would go stale once merged.

## Before starting work

1. Read `docs/wiki/index.md` if it exists. If it does not, say so once and
   continue; do not create it unasked.
2. Open the pages the index lists for the area you are about to touch
   (module, subsystem, feature). Treat them as facts about this project;
   when the code disagrees with a page, trust the code and note the drift.
3. If `.ai/kenkeep/` exists, the project uses kenkeep: its root index is
   the entry point instead, and notes are recalled through it.

## When you learn something durable

Durable means: it would save the next person an investigation. Examples: a
non-obvious dependency, why a decision was taken, a trap in a tool or
module, a procedure that is not in the README. Not durable: progress notes,
ticket status, anything the code states plainly.

At the end of the task, propose the addition; never write it unasked:

- Without kenkeep: propose a page or a section for `docs/wiki/`, under the
  area folder it belongs to (module, subsystem or feature) and with a `Type:`
  header, in the same shape as the existing pages (title, one-paragraph
  purpose, a "researched against" line with date and versions, tables for
  id/label mappings, links to code paths), plus the index line that points to
  it. Put the ticket it came from on an `Origin:` line; it is provenance only,
  so the page must still make sense without it.
- With kenkeep: propose `/kk-add` with the fact in one or two sentences, or
  leave it for `/kk-curate` at session end.

The reviewer reads the page in the same merge request as the code.
