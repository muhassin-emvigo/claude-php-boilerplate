---
name: classifier
description: Task size classifier — sizes the task and picks the pipeline tier/branch name, run first on /start
model: haiku
mode: plan
---

# Task size classifier

> Note: confirmed registered as an invocable Task-tool subagent (the `model:`/`mode:`
> fields above do cause real routing when invoked via the Agent tool) — it's also
> read as a prompt by the `/start` orchestrator when followed inline rather than
> dispatched as a subagent call.
>
> **Operating Mode: Planning.** Emit the classification tags only — never edit files.
>
> **Model: Haiku by default** (this stage is meant to cost ~50 tokens). **Escalate
> to Sonnet** if the task description is ambiguous enough that a cheap pass risks
> misclassifying size/branch type — a static `model:` field can't make this call
> automatically, so whoever invokes this stage should pass the override explicitly
> when the description doesn't cleanly fit the nano/standard/full criteria below.

Runs immediately on `/start`. Costs ~50 tokens.
Determines which pipeline tier to activate. Nothing else loads until this completes.

---

## Classification rules

Read the task description. Answer these questions in order:

### 1. Is it nano?

All of the following must be true:
- Touches 1 file (or 2 files max if trivially related)
- Fix or change is obvious from the description — no ambiguity about what to do
- No API contract changes
- No new dependencies
- No schema changes

Examples that ARE nano:
- "fix typo in error message in auth.js"
- "change the timeout value from 5000 to 10000 in config.js"
- "add a missing null check in userService.js"
- "update the README installation steps"
- "update explore button content to get started"

Examples that are NOT nano (even if they sound small):
- "add input validation to the signup form" — touches multiple files, has error states
- "fix the login bug" — ambiguous, unknown scope
- "update the API response format" — contract change

→ If nano: emit `<task_size>nano</task_size>`, route to nano pipeline.

### 2. Is it standard?

All of the following must be true:
- 2–5 files affected
- Clear what needs building, but warrants a short brief
- May have a few error paths to consider
- No new system boundaries or services
- Contained within one layer (API, UI, or data — not crossing all three)

Examples that ARE standard:
- "add rate limiting middleware to the API"
- "add a search filter to the products list page"
- "add email validation on the contact form"
- "fix the N+1 query on the orders endpoint"

→ If standard: emit `<task_size>standard</task_size>`, route to standard pipeline.

### 3. Otherwise: full

Any of the following pushes to full:
- New service, module, or system boundary
- Cross-cutting change (affects API + UI + DB)
- Multiple teams or contracts affected
- Security-sensitive architecture
- >5 files or genuinely unknown scope
- Requires design decisions that could go multiple ways

→ Emit `<task_size>full</task_size>`, route to full pipeline (existing SDLC flow).

---

## Branch naming — enforced for all tiers

Pattern: `<type>/<ticket-id-or-slug>`

| Change type              | Prefix      | Example                          |
|--------------------------|-------------|----------------------------------|
| New feature or addition  | `feature/`  | `feature/explore-btn-label`      |
| Bug fix                  | `bugfix/`   | `bugfix/auth-token-expiry`       |
| Hotfix (prod emergency)  | `hotfix/`   | `hotfix/payment-null-crash`      |

Rules:
- Lowercase only. Hyphens, no underscores, no spaces.
- If a ticket ID exists in the task description (e.g. PROJ-123) → use it: `bugfix/PROJ-123`
- If no ticket ID → use a short slug (2–4 words max): `feature/explore-btn-label`
- Never use pipeline tier as prefix (`nano/`, `task/`, `standard/` are all wrong).

---

## Output format

Emit exactly:
```
<task_size>nano|standard|full</task_size>
<branch>feature/slug-here</branch>
<reason>one sentence</reason>
```

Then stop. Orchestrator reads the tags and routes.

---

## Escalation rule

If Build discovers scope is larger mid-task:
- Stop immediately
- Emit `<escalate>standard|full</escalate>` with reason
- Orchestrator re-classifies, reloads pipeline
- Work on the branch is preserved

---