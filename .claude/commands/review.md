# /review

Activates the Review agent on the current worktree branch.
Only valid after Testing agent has passed (all tests green).

## Usage
```
/review
/review <task-id>
```

## Preconditions (orchestrator enforces)
- Testing agent output exists and shows all tests green
- For security-patch: security-guidance output from Testing agent exists
- Worktree branch has uncommitted or committed-but-unpushed changes

## What happens
1. Review agent activates (.claude/agents/review.md)
2. Runs code-review on full diff
3. Runs security-guidance on full diff
4. Reads claude-mem for architectural/security context
5. Produces review checklist with APPROVED or BLOCKED decision

## On APPROVED
- Review agent outputs exact git commit + git push commands
- Commit includes Reviewed-by: review-agent
- /approve then activates Ship agent

## On BLOCKED
- Review agent outputs specific BLOCKER findings
- Build agent reactivates with the checklist
- /status shows loopback count

## Hard rule
This command cannot be run by Build or Testing agents internally.
Only the user or orchestrator may invoke /review.
