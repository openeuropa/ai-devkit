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
`Type:` header: `decision` (a scoped architectural decision), `convention`
(where things live, how they are named), `pattern` (reusable solution) or
`pitfall` (a trap to avoid). Decisions use the decision shape (Context /
Decision / Consequences with a Status / Date / Type / Origin header);
conventions, patterns and pitfalls use the reference shape. Every page is
named by slug, not numbered. The starter skeleton (an `index.md` plus one
example per type) ships with this skill under `references/`; copy from there
when a project needs a wiki.

### Decision status

The `Status:` header on a decision-shape page takes one of:

- `accepted` — the default; a merged decision in effect. A new page starts
  here, because the merge is the acceptance (see status-vocabulary-decision).
- `superseded by <area>/<slug>` — replaced by a specific later decision. The
  file stays; the status points to its successor's slug path.
- `deprecated` — no longer applies, with no replacement (nothing superseded
  it; the thing it decided about went away).

There is no `proposed` state: a decision under review is tracked by its open
merge request, not by a header value that would go stale once merged.

## Before starting work

The wiki is opt-in: a project has one when `docs/wiki/index.md` exists. The
devkit never creates it, and you do not create it unasked.

1. If `.ai/kenkeep/` exists, the project uses kenkeep: its root index is the
   entry point, and notes are recalled through it. Skip the rest of this
   section.
2. Otherwise, if `docs/wiki/index.md` exists, read it, then open the pages it
   lists for the area you are about to touch (module, subsystem, feature).
   Treat them as facts about this project; when the code disagrees with a
   page, trust the code and note the drift.
3. If neither exists, the project has not opted into a wiki. Say nothing about
   it and proceed with the task; the offer to set one up comes at the end, and
   only if the work produced something durable (see below).

## When you learn something durable

Durable means: it would save the next person an investigation. Examples: a
non-obvious dependency, why a decision was taken, a trap in a tool or
module, a procedure that is not in the README. Not durable: progress notes,
ticket status, anything the code states plainly.

At the end of the task, propose the addition; never write it unasked:

- No wiki yet (no `docs/wiki/index.md`, no kenkeep): the project has not opted
  in. Offer to set one up — copy the starter skeleton from this skill's
  `references/` (an `index.md` plus one example per type) into `docs/wiki/`,
  then add the page for what you found. State the finding so the offer has a
  payoff, and wait for a yes; do not create any file unasked. If there is
  nothing durable to record, do not offer.
- With a wiki, without kenkeep: propose a page or a section for `docs/wiki/`,
  under the area folder it belongs to (module, subsystem or feature) and with
  a `Type:` header, in the same shape as the existing pages (title,
  one-paragraph purpose, a "researched against" line with date and versions,
  tables for id/label mappings, links to code paths), plus the index line that
  points to it. Put the ticket it came from on an `Origin:` line; it is
  provenance only, so the page must still make sense without it.
- With kenkeep: propose `/kk-add` with the fact in one or two sentences, or
  leave it for `/kk-curate` at session end.

The reviewer reads the page in the same merge request as the code.
