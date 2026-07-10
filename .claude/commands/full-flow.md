# /full-flow

Runs a requirement through the full 16-agent pipeline, start to finish: Classifier →
CEO → Architecture → Eng → Design → Build → Unit Testing → QA Testing → Security
Testing → Performance Testing → Bug Fixing → Code Review → Approver → ADR →
Documentation → PR.

## Usage

```
/full-flow docs/requirements/<file>.md
```

## What happens

Read and follow `.claude/workflows/pipeline-full-16.md` in full — it defines the
exact stage order (reordered from a flat role list so quality-gate agents run after
Build, since they need real code to check), which conditional stages to skip and
when, each stage's model-escalation criteria, and how `docs/progress-<task-id>.md`
tracks the run.

For each stage:
1. Load the corresponding agent file from `.claude/agents/`.
2. Use its `model:` (or the documented escalation model, if criteria apply) and
   `mode:` to decide whether to stop for approval or proceed automatically.
3. Ten stages are standalone subagents — invoke via the Agent tool: architect,
   test-writer, security-auditor, performance-tester, bug-fixer, reviewer, approver,
   adr, doc-writer, pr-description. Six stages are pipeline-stage prompt files, not
   registered subagents — follow their instructions directly in the main session,
   per the existing `.claude/workflows/orchestrator.md` pattern: classifier, ceo,
   eng, design, build, testing.
4. Append a line to `docs/progress-<task-id>.md` after each stage.

Stops after **PR** (stage 16) with a generated PR description. It does not merge,
tag, or push — run `/ship` separately when you're ready, per this project's
commit-gate rule (only Review/Ship output may run `git commit`/`push`/`merge`).

## Output

- `docs/progress-<task-id>.md` — the running record of every stage
- `docs/spec-<task-id>.md` — Eng's technical spec (if that stage ran)
- `docs/design-<task-id>.md` — Design's spec (if that stage ran)
- `docs/adr/<n>-<slug>.md` — ADR (if that stage ran)
- Updated `docs/requirements/<file>.md` `Status` field
- A generated PR description as the final message

## Arguments

`$ARGUMENTS` — path to a requirement file under `docs/requirements/`. Required.
