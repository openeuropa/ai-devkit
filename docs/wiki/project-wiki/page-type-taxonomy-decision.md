# Drop the ADR page type

- Status: accepted
- Date: 2026-11-06
- Type: decision
- Origin: OEL-5055

## Context

An earlier revision of wiki-structure-and-maintenance-decision defined two
decision-shape page types: `adr`, a globally numbered architectural decision
(`adr-NNNN-<slug>.md`), and `decision`, a lighter slug-named scoped decision.
Grouping pages by area while keeping ADR numbering global created friction: no
single ordered decision log, a next-free-number scan across every area folder
to name a new ADR, and number collisions when two concurrent PRs in different
areas claimed the same number. The number also carried no information the slug
and the index did not already provide.

## Decision

Drop `adr` as a page type. Keep a single decision-shape type, `decision`,
named by slug with no number. A cross-area decision lives in an `architecture/`
area. Supersession points at the replacement's slug path
(`superseded by <area>/<slug>`) rather than a number. The three former ADRs
under `project-wiki/` become `decision` pages.

## Options considered

- **Keep `adr` numbered and co-located in one directory** - the conventional
  ADR practice. Rejected: it fights the by-area grouping the wiki is built on.
- **Keep `adr` numbered but scattered by area** - the previous state.
  Rejected: the global numbering needs a cross-area scan and collides on
  concurrent PRs, for no benefit over the slug.
- **One slug-named `decision` type, no numbers** - chosen. Removes the
  scattered log, the next-number lookup and the collisions, and keeps the
  by-area grouping intact.

## Consequences

- Easier: naming a decision needs no number lookup; no collisions; one page
  type instead of two; the slug and the index are the only identifiers.
- Harder: no globally ordered decision log (the index, grouped by area, is the
  only listing); supersession is by slug path, which breaks if a page is
  renamed (slugs are therefore not reused).
