# /approve

Approves the current gate and advances to the next agent.

## Usage
```
/approve
/approve with notes: <optional notes for next agent>
```

## Valid at
- After CEO brief: approves CEO gate, routes to Eng or Build (per flow)
- After Eng spec: approves Eng gate, routes to Design or Build
- After Design spec: approves Design gate, routes to Build
- After Review checklist: only valid if decision is APPROVED (zero BLOCKERs)

## What happens
Orchestrator reads current stage, verifies gate condition is met, activates next agent.

## Hard rule
/approve at Review stage is only valid when:
- Review agent has output decision: APPROVED
- Zero BLOCKERs in review checklist
- code-review and security-guidance both ran

If these conditions are not met, /approve is rejected and orchestrator explains why.

## Arguments
$ARGUMENTS — optional notes appended to context passed to the next agent.
