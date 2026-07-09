# Documentation

Start here. Read these in order:

0. **[00-first-time-setup.md](00-first-time-setup.md)** — New machine or new developer? Start here — clone to a fully installed, running site.
1. **[01-getting-started.md](01-getting-started.md)** — Already installed? Start the website and open it (no technical knowledge needed).
2. **[02-how-it-works.md](02-how-it-works.md)** — A simple explanation of the pieces involved.
3. **[03-testing-the-site.md](03-testing-the-site.md)** — How to check everything is working correctly.
4. **[04-making-business-logic-changes.md](04-making-business-logic-changes.md)** — For developers: where to make code changes, and how to reuse this setup for other projects.
5. **[05-requesting-a-change.md](05-requesting-a-change.md)** — Anyone (technical or not): want something new or different? Start here.
6. **[06-ai-tooling-status.md](06-ai-tooling-status.md)** — Developers using the `.claude/agents/` pipeline: what's actually installed (gstack/claude-mem status) and how to check local token usage.

## Architecture Decision Records

**[adr/](adr/)** — Records of significant, hard-to-reverse technical decisions (and the alternatives that
were considered and rejected) made for individual features, kept separate from the per-change docs below
so the *why* behind a design survives even after the requirement/spec/progress docs for that change are
no longer actively referenced. Start with [adr/1-fefo-batch-inventory-architecture.md](adr/1-fefo-batch-inventory-architecture.md).

## Per-change documentation

Each business-logic change made through the `.claude/agents/` pipeline (see
[04-making-business-logic-changes.md](04-making-business-logic-changes.md)) leaves a paper trail in
`docs/`, named after the change: `requirements/<date>-<slug>.md` (the original ask),
`spec-<slug>.md` (the engineering spec), `progress-<slug>.md` (stage-by-stage build log), and, for
larger changes, `build-summary-<slug>.md` and `qa-scenario-verification-<slug>.md`. These aren't listed
individually here — browse `docs/` directly, or start from a shipped example:
[docs/progress-batch-based-inventory-management.md](progress-batch-based-inventory-management.md) and
the corresponding module at
[app/code/vendor/Rgd/Inventory/](../app/code/vendor/Rgd/Inventory/README.md).
