# /status

Shows current pipeline state for the active task.

## Usage
```
/status
/status <task-id>
```

## Output
```
Task:        <task-id>
Flow:        <flow_type>
Stage:       <current stage name>
Gate:        PENDING / PASSED / BLOCKED
Last agent:  <agent name>
Loopbacks:   <count> (Build→Test: N, Review→Build: N)

Docs:
  Brief:     docs/spec-<id>.md  [exists / missing]
  Spec:      docs/spec-<id>.md  [exists / missing]
  Design:    docs/design-<id>.md [exists / missing]
  Progress:  docs/progress.md   [last updated: <timestamp>]

Branch:      task/<id>  [local / pushed / merged]
Commit gate: LOCKED (Review: pending) / OPEN (Review: approved)
```

## Arguments
$ARGUMENTS — optional task-id. If omitted, shows most recent active task.
