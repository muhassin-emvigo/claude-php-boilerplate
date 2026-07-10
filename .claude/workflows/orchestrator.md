# Orchestrator

## Role
Sequence agents. Enforce gates. Route by flow_type. Never execute work directly.

## Machine-readable source
The phase graph below is also modeled in `.claude/workflows/workflow-manifest.yaml` —
use that file when you need to reason programmatically about phase dependencies
(`depends_on`, `strength: required|recommended`), which phases are `optional`
per flow_type, `run_once` guards, or external prerequisites (e.g.
`security-guidance` before Testing/Review). This file remains the authoritative
prose walkthrough for a human or agent reading top-to-bottom; keep both in sync
if the phase sequence ever changes.

## On every task

```
0. Run classifier FIRST (.claude/agents/classifier.md) — ~50 tokens
   → Reads task description only
   → Emits <task_size>nano|standard|full</task_size>

   nano     → load pipeline-nano.md     (Build lean → Review lean → done)
   standard → load pipeline-standard.md (CEO lean → Build → Review → Ship lean)
   full     → continue steps below      (full 7-agent pipeline)

   Escalation: if Build emits <escalate>standard|full</escalate> mid-task
   → pause, re-classify, reload appropriate pipeline, resume from Build

1. [FULL ONLY] Activate CEO agent (.claude/agents/ceo.md)
   → Wait for: task brief + flow_type tag
   → Gate: /plan-ceo-review passed (except hotfix: self-approved)

2. Read flow_type tag. Load flow definition from .cursor/workflows/flow-<type>.md.

3. Follow the stage sequence for that flow exactly:

   backend-feature:   CEO → Eng → Build → Testing → Review → Ship
   frontend-feature:  CEO → Eng → Design → Build → Testing → Review → Ship
   hotfix:            CEO → Build → Testing → Review → Ship
   design-only:       CEO → Design → Review → Ship
   security-patch:    CEO → Eng → Build → Testing → Review → Ship

4. At each stage:
   a. Activate the agent for that stage
   b. Pass all prior stage outputs as context
   c. Wait for agent output + gate condition
   d. On gate pass: activate next agent
   e. On gate fail: return to the specified agent with failure notes

5. Commit gate (enforced by orchestrator, not just Review agent):
   RULE: git commit / git push / git merge commands may only appear in:
   - Review agent output (the commit itself)
   - Ship agent output (the merge and tag)
   Any other agent outputting git commit commands is an error. Stop and flag.

6. Ship agent runs only after:
   - Review agent decision: APPROVED
   - Branch is pushed with Reviewed-by: review-agent in commit message
   - Both conditions verified before activating Ship
```

## Loopback rules

| From         | Condition                        | Return to | Pass context                          |
|--------------|-----------------------------------|-----------|----------------------------------------|
| Testing      | Any test fails                   | Build     | Failure log from docs/progress.md     |
| Testing      | security-guidance flags partial fix | Build  | security-guidance output              |
| Review       | Any BLOCKER finding              | Build     | Full review checklist                 |
| Review       | Spec compliance failure          | Eng       | Specific compliance gaps              |

Maximum loopback depth: 3 cycles per stage pair. On 4th cycle — escalate to CEO agent for scope reassessment.

## Context passing between agents

Each agent receives:
- Its own agent definition file
- The flow definition file for the current flow_type
- All prior stage outputs (brief, spec, design spec, test results)
- The plugin-registry.md for plugin reference

Do not summarise prior outputs — pass them in full.

## Gate summary

| Stage  | Gate command/condition                          | Hard block if not met |
|--------|-------------------------------------------------|-----------------------|
| CEO    | /plan-ceo-review (or hotfix self-approval rule) | Yes                   |
| Eng    | /plan-eng-review                                | Yes                   |
| Design | /plan-design-review                             | Yes                   |
| Build  | code-review self-review clean                   | Yes                   |
| Testing| All tests green + security-guidance (sec-patch) | Yes — loops to Build  |
| Review | Zero BLOCKERs from code-review + security-guidance | Yes — loops to Build |
| Ship   | Reviewed-by: review-agent in commit             | Yes — hard stop       |
