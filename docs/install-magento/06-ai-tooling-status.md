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

## claude-mem — installed (2026-07-10)

Referenced throughout `.claude/agents/` (`ceo.md`, `design.md`, `eng.md`, `testing.md`,
`review.md`, `build.md`, `ship.md`) as the mechanism for reading/writing persistent
context across tasks (architectural decisions, design tokens, security patterns).

**Confirmed status:** installed globally via `npm install -g claude-mem && claude-mem
install` (third-party plugin, [thedotmack/claude-mem](https://github.com/thedotmack/claude-mem)
— not an Anthropic product). Plugin and marketplace registered under
`~/.claude/plugins/marketplaces/thedotmack`. Worker daemon confirmed healthy at
`http://127.0.0.1:37777` (`npx claude-mem doctor` — all required checks passed). A
`SessionStart` hook shipped with the plugin restarts the worker automatically on
`startup`/`clear`/`compact`, so it doesn't need to be started by hand each session.

**Known rough edge on this Windows/XAMPP setup:** the plugin's own `claude-mem start`
wrapper (`Start-Process -WindowStyle Hidden` via PowerShell `-EncodedCommand`) failed
silently here — running the underlying worker script directly with `bun` worked fine.
If the worker ever shows as not running and the `SessionStart` hook doesn't recover
it, run `npx claude-mem status` / `npx claude-mem doctor` to check, and start it
manually with the command in `~/.claude-mem/logs/claude-mem-*.log` if needed. `npx
claude-mem status` may also report `Dependencies: degraded (Claude CLI setup
required)` even when `doctor` shows all checks passing — this is a known cosmetic
mismatch on this machine (the `claude` CLI binary isn't on the sandboxed shell's
PATH used for these checks) and hasn't been shown to affect memory capture.

**Storage:** everything stays local at `~/.claude-mem` on this machine (ChromaDB
vector store), nothing sent off-box. Memory injection starts on the *second* session
in a project — the first session just captures. Existing agent file references to
`claude-mem` read/write instructions are now live rather than no-ops.

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
