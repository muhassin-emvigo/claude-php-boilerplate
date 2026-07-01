---
description: Multi-agent code review — security, performance, accessibility, and documentation quality
---

# Comprehensive Code Review

> **Auto-review note:** Code review runs automatically on every `git commit` via the pre-commit hook.
> Use this command for on-demand review outside of a commit — reviewing a file mid-development,
> a colleague's patch, or a PR URL.

Review the following: **$ARGUMENTS**

---

Act as an ensemble of five senior engineers, each reviewing from their own lens.
Run all agents in parallel. Collect all findings. Then produce a unified report.

Each agent scores every finding **0–100 (confidence)**. Only report findings scored **≥ 70**.
Do not repeat the same finding across agents.

---

## Agent 1 — Security Officer

Specialisations: OWASP Top 10, injection, auth, secrets, supply chain.

Check for:
- **Injection** — SQL, command, path traversal, template injection, SSRF
- **Auth flaws** — missing auth checks, broken RBAC, JWT misuse, session fixation
- **Secrets exposure** — hardcoded credentials, API keys, tokens in source or logs
- **XSS vectors** — innerHTML, dangerouslySetInnerHTML, document.write, eval()
- **Insecure dependencies** — known CVEs, unpinned versions, unused packages
- **Cryptography** — weak algorithms (MD5, SHA1), hardcoded salts, missing HTTPS enforcement
- **Input validation** — missing sanitization, over-trusting user input, missing allowlists
- **Error disclosure** — stack traces or internal paths leaking to clients

Output each finding as:
```
🔴 SEC-[n] | Severity: CRITICAL/HIGH/MEDIUM | File:Line
Description + exploit scenario + remediation
```

---

## Agent 2 — Performance Analyst

Specialisations: algorithmic complexity, memory, I/O, rendering.

Check for:
- **N+1 queries** — database calls inside loops
- **Algorithmic complexity** — O(n²) or worse in hot paths
- **Memory leaks** — unclosed streams, event listeners never removed, growing caches
- **Blocking I/O** — synchronous file/network calls on the main thread
- **Unbounded operations** — missing pagination, no request size limits, infinite loops risk
- **Bundle size** (frontend) — large imports, missing tree-shaking, unoptimised images
- **Render performance** (Magento Blocks/ViewModels) — missing caching, unnecessary collection loading in templates
- **Database** — missing indexes on filtered/sorted columns, unbounded queries, no connection pooling, ObjectManager usage

Output each finding as:
```
🟡 PERF-[n] | Impact: HIGH/MEDIUM/LOW | File:Line
Description + estimated impact + fix
```

---

## Agent 3 — Accessibility Auditor

Specialisations: WCAG 2.1 AA, keyboard navigation, screen readers, colour contrast.
*Skip this agent if no frontend files (.phtml, .xml, .html) are present in the diff.*

Check for:
- **Semantic HTML** — missing landmarks (`<main>`, `<nav>`, `<header>`), wrong element choice (div as button)
- **Images** — missing or empty `alt` text, decorative images not marked `alt=""`
- **Keyboard navigation** — interactive elements not keyboard-reachable, missing focus styles, wrong tab order
- **ARIA** — missing `aria-label`, wrong roles, redundant ARIA on native elements
- **Forms** — inputs missing `<label>` or `aria-labelledby`, no error announcements
- **Colour contrast** — hardcoded colours that may fail WCAG AA (4.5:1 text, 3:1 UI components)
- **Motion** — missing `prefers-reduced-motion` for animations
- **Dynamic content** — live regions missing for async updates, focus not managed after modal open/close

Output each finding as:
```
🔵 A11Y-[n] | WCAG: [criterion] | File:Line
Description + user impact + fix
```

---

## Agent 4 — Documentation Inspector

Specialisations: JSDoc, inline comments, README accuracy, changelog hygiene.

Check for:
- **Missing PHPDoc** — exported functions, classes, or types with no `@param`, `@return`, or `@description`
- **Stale comments** — comments that no longer match the code they describe
- **Cryptic logic** — complex conditionals, regex, or algorithms with no explanation
- **TODO/FIXME debt** — unresolved markers with no ticket reference
- **README drift** — new env vars, endpoints, or commands not reflected in README or `.env.example`
- **CHANGELOG gaps** — user-facing changes not documented under `## [Unreleased]`
- **Magic values** — hardcoded numbers or strings with no named constant or comment explaining their origin
- **Dead code** — commented-out blocks, unused exports, unreachable branches

Output each finding as:
```
📝 DOC-[n] | Priority: HIGH/MEDIUM/LOW | File:Line
Description + suggested improvement
```

---

## Agent 5 — Correctness & Quality Reviewer

Specialisations: logic, edge cases, error handling, test coverage.

Check for:
- **Logic errors** — off-by-one, incorrect conditions, wrong operator (`=` vs `==` vs `===`)
- **Unhandled edge cases** — null/undefined input, empty arrays, zero values, concurrent access
- **Error handling** — swallowed exceptions, missing try/catch, errors not propagated
- **Race conditions** — async operations without proper sequencing or locking
- **Type safety** — implicit `any`, missing type guards, unsafe casts
- **Test coverage** — new code with no tests, tests that only cover happy path
- **Code duplication** — logic copied instead of extracted into a utility
- **Naming** — misleading function/variable names, inconsistent conventions with the rest of the codebase

Output each finding as:
```
⚪ QA-[n] | Severity: HIGH/MEDIUM/LOW | File:Line
Description + expected vs actual behaviour + fix
```

---

## Final Unified Report

After all five agents complete, produce:

### Summary
One paragraph: overall quality, most significant concerns, key strengths.

### Findings Table
| ID | Agent | Severity | File | Line | Issue (short) |
|----|-------|----------|------|------|---------------|

Sort by: CRITICAL → HIGH → MEDIUM → LOW. Group by agent within severity.

### What Looks Good
- Positive observations (max 5 bullets — be specific, not generic)

### Verdict
Choose one:
- ✅ **Approve** — no blocking issues
- ⚠️ **Approve with comments** — issues present but not blocking
- 🚫 **Request Changes** — one or more CRITICAL or HIGH findings must be fixed first

---

