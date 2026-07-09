# Pipeline: Full 16-agent flow

Activated by `/full-flow docs/requirements/<file>.md`. Runs every named agent role
end to end for a single requirement, respecting each agent's `model:`/`mode:`
frontmatter (see `.claude/agents/*.md`).

## Order — and why it differs from a flat role list

Requirement input naturally groups into: size the work, decide what to build, decide
how to build it, build it, verify it, get it approved, record it, hand it off. Code
can't be tested, reviewed, or approved before it exists, so every quality-gate agent
(Unit Testing, QA Testing, Security Testing, Performance Testing, Code Review,
Approver) runs **after** Build, not before.

```
 1. Classifier         (.claude/agents/classifier.md)          — haiku, plan
 2. CEO                (.claude/agents/ceo.md)                 — sonnet→fable, plan
 3. Architecture        (.claude/agents/architect.md)           — opus, plan          [conditional]
 4. Eng                (.claude/agents/eng.md)                 — sonnet→opus, acceptEdits (spec doc only)
 5. Design              (.claude/agents/design.md)               — sonnet, plan         [conditional: frontend]
 6. Build               (.claude/agents/build.md)                — haiku→sonnet, auto
 7. Unit Testing        (.claude/agents/test-writer.md)          — sonnet, acceptEdits
 8. QA/Scenario Testing (.claude/agents/testing.md)              — sonnet, plan
 9. Security Testing    (.claude/agents/security-auditor.md)     — opus, plan
10. Performance Testing (.claude/agents/performance-tester.md)   — opus, plan
11. Bug Fixing          (.claude/agents/bug-fixer.md)            — sonnet→opus, acceptEdits [conditional: only if 7-10 found blocking issues]
12. Code Review         (.claude/agents/reviewer.md)             — opus, plan
13. Approver            (.claude/agents/approver.md)             — sonnet→fable, decision
14. ADR                (.claude/agents/adr.md)                  — opus, plan           [conditional: significant decision made]
15. Documentation       (.claude/agents/doc-writer.md)           — sonnet, acceptEdits
16. PR                 (.claude/agents/pr-description.md)       — sonnet, acceptEdits
```

Stops at **PR** — it does not merge, tag, or push (that's `.claude/agents/ship.md`,
run separately via `/ship` once you're ready, per this project's existing commit-gate
rule: only Review/Ship-stage output may run `git commit`/`push`/`merge`).

## Progress tracking

Before stage 1, create `docs/progress-<task-id>.md` (task-id = the requirement file's
slug). After every stage, append a line:

```
- [x] 1. Classifier — <task_size>, branch <name>
- [x] 2. CEO — brief written
- [ ] 3. Architecture — skipped (no new service boundary)
- [x] 4. Eng — spec at docs/spec-<task-id>.md
...
```

This file is the persistent "start to complete" record — check it any time to see
exactly where a run stands, even across separate sessions.

## Gating, per stage `mode:`

- **plan** — present the stage's output in full, then stop. Wait for explicit
  approval (any affirmative reply) before continuing to the next stage.
- **acceptEdits** — make the edits directly, summarize, continue automatically.
- **auto** — proceed autonomously through the stage, summarize at the end, continue
  automatically.
- **decision** — present APPROVED/BLOCKED verdict, then stop. BLOCKED routes back to
  the stage(s) named in the verdict rather than continuing forward.

## Model escalation

Each agent file documents its own escalation criteria (e.g. "Sonnet by default,
escalate to Opus for X"). Check the brief/spec/findings so far against those
criteria before each stage; if they apply, note the escalation and use the
higher-tier model for that stage.

## Conditional stages — skip rules

- **Architecture (3)**: run only if the CEO brief involves a new service boundary,
  schema change, or a Plugin/Observer/Event design decision. Otherwise mark skipped
  in progress.md with a one-line reason.
- **Design (5)**: run only if `flow_type` is `frontend-feature` or the brief
  otherwise involves UI work.
- **Bug Fixing (11)**: run only if stages 7–10 reported any failing test, BLOCKER, or
  CRITICAL/HIGH finding. On completion, re-run stages 7–10 once against the fix
  before continuing to Code Review. If issues persist after one Bug Fixing pass,
  stop and escalate to the user rather than looping indefinitely.
- **ADR (14)**: run only if Architecture (3) or Eng (4) recorded a decision meeting
  the criteria in `.claude/agents/adr.md` ("When an ADR is worth writing").

## Requirement file handling

On completion (or if BLOCKED and stopped), update the source
`docs/requirements/<file>.md`'s `Status` field: `New` → `In Progress` while running,
`Done` on a clean finish through PR, or leave `In Progress` with a note if stopped
early pending user input.

## Total steps

16 possible stages, 12 always run (3, 5, 11, 14 are conditional). Typical full run:
~12-13 stages with 6 approval gates (plan/decision-mode stages).
