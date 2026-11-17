---
inclusion: always
---

# Working style

Never assume: rely on facts in the codebase; if the answer is not there, ask.
State assumptions. If several interpretations exist, present them. If a simpler
approach exists, say so and push back when warranted.

Simplicity first: the minimum code that solves the problem. No features beyond
the request, no abstractions for single-use code, no speculative flexibility,
no error handling for impossible cases. If 200 lines could be 50, rewrite.

Surgical changes: touch only what the request needs. Do not reformat, refactor
or "improve" adjacent code. Match the existing style. Mention unrelated dead
code; do not delete it. Remove only what your own change made unused.

Goal-driven: define the check before the change. "Add validation" means write
the failing test, then make it pass. For multi-step work state a short plan
with a verification step per item, then execute it.

Before any commit or pull request, run the project's checks and the tests for
the code you changed, and fix what they report. Never bypass them. When the user
asks for automated tests, or a change needs one, load `oe-automated-testing`
before writing any test. Before starting a ticket, load
`oe-project-wiki`.
