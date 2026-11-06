# Decision status vocabulary

- Status: accepted
- Date: 2026-10-29
- Type: decision
- Origin: OEL-5055

## Context

wiki-structure-and-maintenance-decision fixed the decision shape with a `Status:`
header but never defined which status values are allowed. Early pages
defaulted to `proposed`, carried over from the conventional decision lifecycle
(proposed → accepted on acceptance).

In this project a decision ships in the same merge request as the code it
describes, and the reviewer reads the page in that MR (see
wiki-structure-and-maintenance-decision, maintenance rule). So the acceptance *is* the
merge: a `proposed` header is only ever true while the MR is open, and must be
flipped to `accepted` before or at merge. That flip is a manual step with no
payoff — review state is already tracked by the open MR — and a forgotten flip
leaves every merged decision permanently claiming it is still up for debate.
wiki-structure-and-maintenance-decision already rejects process steps of this kind (a
presence-only CI gate) as trivially gamed and bypass-training.

## Decision

The `Status:` header on a decision-shape page (`Type: decision`) takes one of
three values:

- **`accepted`** — the default; a merged decision in effect. A new page starts
  here, because merging the MR is the act of acceptance.
- **`superseded by <area>/<slug>`** — a later decision replaced this one. The
  file is kept (slugs are never reused) and the status points to its
  successor's slug path.
- **`deprecated`** — the decision no longer applies and nothing replaced it
  (the thing it decided about went away). Distinct from `superseded`, which
  requires a successor to point at.

There is no `proposed` state. A decision under review is tracked by its open
merge request, not by a header value that goes stale the moment it merges.

## Options considered

- **Keep `proposed` as the default, flip to `accepted` at merge** — the
  conventional decision lifecycle. Rejected: the flip is a manual step the MR
  already makes redundant, and a missed flip is silent and permanent.
- **`accepted` as the default, no `proposed`** — chosen. Review lives in the
  MR; the committed file always reflects the merged, in-effect state.

## Consequences

- Easier: a new decision is correct the moment it is written; no flip to
  remember, nothing to go stale; the status set is small and unambiguous.
- Harder: nothing material. A decision still being debated has no in-file
  marker, but that state belongs to the MR, not the merged wiki.
