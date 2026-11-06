# Project wiki

Durable knowledge for the ai-devkit project: decisions, conventions and
patterns worth reading before the source code. Read this index first, then
open only the pages listed for the area you are about to touch. Pages are
grouped by area; each line names the page, its type, and what you will find.

## project-wiki

- project-wiki/wiki-hosting-location-decision.md - (decision) the project wiki
  lives in-repo under `docs/wiki/`; local-only rejected, kenkeep an optional
  overlay.
- project-wiki/wiki-structure-and-maintenance-decision.md - (decision) page
  taxonomy (grouped by area, type in the header), the layered maintenance rule
  (reviewer is the gate), and issue-tracker linkage.
- project-wiki/status-vocabulary-decision.md - (decision) the allowed `Status:`
  values (accepted, superseded by, deprecated) and why there is no `proposed`.
- project-wiki/page-type-taxonomy-decision.md - (decision) collapse the `adr`
  and `decision` types into one slug-named `decision` type; why numbering was
  dropped.
