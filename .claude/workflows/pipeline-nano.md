# Pipeline: Nano

Activated when classifier emits `<task_size>nano</task_size>`.

Agents loaded: Build (lean mode), Review (lean mode)
Agents skipped: CEO, Eng, Design, Testing, Ship (merged into Review)
Plugins loaded: code-review only
claude-mem: read-only (no write for nano tasks)

Target token budget: ~400 tokens total

---

## Build agent — lean mode

No `/execute-plan`. No `/brainstorm`. No spec file.

1. Read the task description directly.
2. Read `claude-mem` — 3-line context pull only: relevant file conventions.
3. Make the change. One focused edit.
4. Run inline `code-review` on the diff (self-review, not full plugin run).
5. Write nothing to `docs/`. No `progress.md` update for nano tasks.

**Branch:** Use the `<branch>` tag emitted by the classifier. Never invent a prefix.
- Feature/addition → `feature/<slug>`
- Bug fix → `bugfix/<slug>`
- Hotfix → `hotfix/<slug>`
- Slug: lowercase, hyphens only, 2–4 words. Use ticket ID if present.

```bash
git checkout -b <branch-from-classifier>
```

**Escalation check:** After reading the file, if the actual change is clearly larger than described — emit `<escalate>standard</escalate>` and stop. Do not proceed.

**Hard rules:**
- No git commit.
- If the change touches more than 2 files — escalate, don't continue.
- Total output: diff + 2-line self-review. Nothing else.
- Do NOT run linters, formatters, or fix pre-existing issues. Not the task.

---

## Review agent — lean mode

No full `code-review` plugin run. No `security-guidance` (unless diff touches auth, input handling, or data access — then mandatory).

1. Read the diff.
2. Quick check: correct? minimal? any risk?
3. Auth/input/data access touched → run `security-guidance`.
4. Decision: APPROVED or BLOCKED.

**If pre-commit hook blocks:**
- The ONLY valid reason is a missing `Reviewed-by: review-agent` token.
- Do NOT run linters. Do NOT run formatters. Do NOT fix pre-existing issues.
- Ensure the token is in the commit message. Retry once.
- Still blocked after token present → stop and report exact hook output. Do not investigate further.

On APPROVED — single block:
```bash
git add -A
git commit -m "<type>(<scope>): <description>

Reviewed-by: review-agent"
git push origin <branch>
git checkout main
git merge --no-ff <branch>
git push origin main
git branch -d <branch>
```

Commit type mapping:
- `feature/*` branch → `feat`
- `bugfix/*` branch  → `fix`
- `hotfix/*` branch  → `fix`

No Ship agent. No tag. No changelog (unless user-visible — one-liner only).
No `/wrapup`. No claude-mem write.

---

## Total steps: 3

1. Classifier → size + branch name
2. Build (lean) → checkout branch, make change, diff
3. Review (lean) → commit + merge

---