# /ship

Activates the Ship agent. Merges, tags, and closes the task.
Only valid after Review agent has issued APPROVED and branch is pushed.

## Usage
```
/ship
/ship <task-id>
```

## Preconditions (orchestrator enforces — hard stop if not met)
1. Review agent decision: APPROVED (in current session or docs/review-<id>.md)
2. Branch pushed to remote: `git ls-remote origin task/<id>` returns a ref
3. Commit on branch contains: `Reviewed-by: review-agent`

If any precondition fails — /ship is rejected with the specific reason.

## What happens
1. Ship agent activates (.claude/agents/ship.md)
2. Runs gstack /ship — merge to main, tag, push
3. Generates changelog entry
4. Runs /wrapup retrospective
5. Writes to claude-mem: retro, new patterns, new tokens, security conventions

## Output
- Merge commit hash
- Tag name
- Changelog entry (appended to CHANGELOG.md)
- Wrapup summary
- claude-mem keys updated

## Arguments
$ARGUMENTS — optional task-id. If omitted, ships the most recent approved task.
