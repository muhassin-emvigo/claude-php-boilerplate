# Pipeline: Standard

Activated when classifier emits `<task_size>standard</task_size>`.

Agents loaded: CEO (lean), Build, Review, Ship (lean)
Agents skipped: Eng, Design, Testing (conditional)
Plugins loaded: code-review, security-guidance (Review), claude-mem

Target token budget: ~1500 tokens

---

## Branch naming

Use the `<branch>` tag emitted by the classifier.
- New feature → `feature/<slug-or-ticket-id>`
- Bug fix     → `bugfix/<slug-or-ticket-id>`
- Hotfix      → `hotfix/<slug-or-ticket-id>`

Slug: lowercase, hyphens, 2–4 words. Ticket ID if present.
Never use `task/`, `standard/`, or pipeline-tier prefixes.

---

## CEO agent — lean mode

No `/office-hours`. No `/brainstorm`. No `/plan-ceo-review` gate.

Produce a brief in one pass:
```
Task:     <one sentence>
Criteria: <2–3 bullets max>
Scope:    <files/areas affected>
Risk:     none | <one-line flag if any>
```

Write to `claude-mem`: task ID + brief summary (2 lines max).
Budget: ~150 tokens. More than that → it's a full task, escalate.

**Escalation check:** Scope unclear or cross-cutting → emit `<escalate>full</escalate>` and stop.

---

## Eng agent — SKIPPED

Exception: CEO brief flags an API contract or DB schema change → Eng activates in lean mode (bullet-list spec only, no `/plan-eng-review` gate).

---

## Design agent — SKIPPED

Standard tasks don't involve UI design. If they do → escalate to full frontend-feature flow.

---

## Build agent — standard mode

Normal Build behavior with one reduction:
- No `/write-plan` — use CEO brief directly
- `/execute-plan` still runs
- `code-review` self-review loop still runs after each chunk
- `docs/progress.md` still updated
- Do NOT run linters, formatters, or fix pre-existing issues

Branch: `<branch-from-classifier>` (feature/, bugfix/, or hotfix/)

---

## Testing agent — conditional

Run only if:
- CEO brief has a risk flag, OR
- Build self-review flags untested critical paths

Skip if change is additive/non-breaking and no risk flagged.
When skipped: add `Tests: skipped (standard/low-risk)` to commit message.

---

## Review agent — standard mode

Full `code-review` on diff — no reduction.
`security-guidance` runs if diff touches auth, input handling, data access, or external calls.

**If pre-commit hook blocks:**
- Only reason: missing `Reviewed-by: review-agent` token.
- Do NOT fix lint or formatting issues.
- Add token, retry once. Still blocked → stop and report.

Same APPROVED/BLOCKED decision. Same commit format.

Commit type:
- `feature/*` → `feat`
- `bugfix/*`  → `fix`
- `hotfix/*`  → `fix`

---

## Ship agent — lean mode

No `/wrapup`. No retrospective.
1. Merge to main, tag if user-visible feature, one-liner changelog.
2. Write to `claude-mem`: task ID + one-line summary only.

---

## Total steps: 4–5

1. Classifier → size + branch name
2. CEO (lean) → brief
3. Build → diff + self-review
4. (Testing — only if flagged)
5. Review → commit
6. Ship (lean) → merge

---