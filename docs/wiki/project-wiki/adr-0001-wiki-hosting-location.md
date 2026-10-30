# Wiki hosting location

- Status: accepted
- Date: 2026-10-21
- Type: adr
- Origin: OEL-5055

## Context

The "wiki-first" model (25/09/2026 meeting) requires the agent to consult a
structured project knowledge base before reading source code. OEL-5055 must
decide where that knowledge base lives. Three options were on the table:

1. In-repo, under `docs/wiki/` in the project being worked on.
2. Local per project, outside the repository (machine-local notes).
3. Hybrid, via "kenkeep" (`.ai/kenkeep/`), layered over the repo.

The seed skill `skills/oe-project-wiki/SKILL.md` already commits to option 1
with option 3 as an optional overlay, but the rationale was never recorded.

## Decision

Durable project knowledge lives **in-repo under `docs/wiki/`**, entered
through `docs/wiki/index.md`.

Local-only hosting is rejected. The kenkeep hybrid is accepted only as an
optional overlay on top of the in-repo base; kenkeep's own definition and
tooling are out of scope for this ticket and treated as an external
dependency.

## Options considered

- **In-repo (`docs/wiki/`)** - travels with the code; versioned and
  reviewable in the same PR as the change; shared by default. Cost: the repo
  carries documentation weight and the maintenance rule must keep pages from
  drifting.
- **Local per project (outside the repo)** - rejected. Invisible to other
  contributors and CI, lost on a fresh clone, cannot be reviewed in a PR.
  Defeats the "shared memory between agent sessions" goal.
- **Hybrid via kenkeep** - an external tool referenced in the skill but not
  defined in this repository. Treated as an optional overlay, not the primary
  store. Out of scope for OEL-5055.

## Consequences

- Easier: knowledge is versioned, reviewed and shipped with the code; agents
  have one canonical entry point.
- Harder: the repo must carry and maintain the pages; an explicit maintenance
  rule is needed to prevent drift (adr-0002).
- Deferred: kenkeep stays a referenced-but-undefined overlay until a separate
  ticket defines it.
