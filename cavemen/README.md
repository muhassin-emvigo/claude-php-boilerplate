# cavemen

A Claude Code plugin that automatically enforces a lean procedure on every
markdown file: minimize token usage, skip unnecessary explanation, and reuse
stored context instead of repeating it across prompts.

## What it does

- **Hook (automatic)** — on every `Read`, `Write`, or `Edit` of a `.md` file:
  - *before* the call: prints a one-line reminder to stay lean and check the
    ledger first.
  - *after* a `Write`/`Edit`: records/refreshes a one-line entry in
    `.claude/cavemen-ledger.md` (path + last-touched time) so future turns
    can check that ledger instead of re-reading the whole file.
- **Command** — `/cavemen <file or topic>` to run the procedure manually.
- **Skill** — `cavemen`, invoked automatically when relevant, documenting the
  same procedure for the model to follow explicitly.

Nothing here is blocking — the hook is advisory only and never stops a tool
call.

## Install

This folder is a self-contained plugin (manifest, hook, command, and skill
all under `cavemen/`). From an **interactive** Claude Code session (this
can't be done from a non-interactive/scripted session):

```
/plugin marketplace add ./cavemen
/plugin install cavemen
```

or use the `/plugin` menu and point it at this directory. Once installed,
the hook runs automatically — no further setup needed.

## Files

```
cavemen/
├── .claude-plugin/plugin.json   # manifest
├── hooks/hooks.json             # Pre/PostToolUse wiring
├── scripts/md-procedure.sh      # the actual logic
├── commands/cavemen.md          # /cavemen slash command
└── skills/cavemen/SKILL.md      # auto-invoked skill
```
