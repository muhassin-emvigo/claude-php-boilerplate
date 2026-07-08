# AI Agent Tooling — Status

A plain record of what's actually installed and working vs. what's referenced but
not set up, so nobody re-discovers this the hard way.

## gstack — NOT installed, but needed

Several files in `.claude/agents/` (`ceo.md`, `design.md`, `eng.md`, `review.md`,
`ship.md`) reference `gstack` plugin commands: `/office-hours`, `/plan-ceo-review`,
`/plan-design-review`, `/plan-eng-review`, `/ship`. Unlike `superpowers` (removed,
see below), this one is intentionally kept — it's the plugin this pipeline actually
depends on.

**Confirmed status:** `gstack` is not on this machine's PATH and is not installed as
a global npm package. If you invoke a pipeline stage that calls a `gstack` command,
that step will not run as written until it's actually installed and configured.

**What still works without it:** every stage's core responsibilities (producing a
brief, a spec, a design doc, running review, etc.) — the `gstack` commands are
structured wrappers around behavior an agent can still do directly by following the
rest of the file's instructions.

## superpowers — removed from the pipeline

Previously referenced in `ceo.md`, `eng.md`, `build.md`, `testing.md`, and `ship.md`
for `/brainstorm`, `/write-plan`, `/execute-plan`, and `/wrapup`. Determined not
needed — every agent file now does that work directly (enumerate approaches, write
the spec, implement, write the retrospective) without depending on the plugin. If
you see a `superpowers` mention left in an agent file, it's only an explanatory
note about the removal, not a live dependency.

## claude-mem — NOT installed

Referenced throughout `.claude/agents/` (`ceo.md`, `design.md`, `eng.md`, `testing.md`,
`review.md`, `build.md`, `ship.md`) as the mechanism for reading/writing persistent
context across tasks (architectural decisions, design tokens, security patterns).

**Confirmed status:** not on PATH, not in global npm packages, no `.claude/plugins`
directory. Any `claude-mem` read/write instruction in those files is currently a
no-op if followed literally.

**What to use instead today:** Claude Code's own built-in cross-session memory
system (the one Claude uses automatically — no setup needed) already captures
project context, and `docs/requirements/` + `docs/adr/` give a human-readable,
version-controlled record that serves the same purpose `claude-mem` was meant to
automate. If `claude-mem` gets installed later, the existing agent file references
to it should still work as written.

## Checking local token usage — `ccusage`

To see how many tokens/cost a Claude Code session has used, from any terminal:

```bash
npx ccusage@latest daily
```

No install needed — `npx` fetches it on demand. Other report modes are available;
run `npx ccusage@latest --help` to see them. There's also a Makefile shortcut:

```bash
make usage
```
