# Bug-fix workflow

Used by `/fix`. Two ways to invoke:

- `/fix <description>` — a bug described inline (a symptom, an error message, a stack trace, a URL to click through).
- `/fix` with no argument — pick the oldest entry under **Open** in `BUGS.md` at the project root.

## Steps

1. **Read the report.** If it references `BUGS.md`, read that entry in full (steps to reproduce, expected vs. actual). If it's inline text, treat the message itself as the report.

2. **Reproduce.** Prefer real evidence over guessing:
   - Runtime/UI bug: check `var/log/system.log` and `var/log/exception.log` for a matching stack trace first. If a browser session is available (Claude in Chrome or similar), drive the reported steps and capture the console/network error directly. If neither is available, say so and ask the reporter for the browser console error and Network tab status for the relevant request — don't guess past this point.
   - Test/CLI bug: run the failing command or test and capture the actual output.
   - If reproduction isn't possible from the given information, stop and list exactly what's missing (steps, environment, error text) — do not proceed on a guess.

3. **Isolate.** Trace the symptom or stack trace back to the specific file/line/class responsible. Read the surrounding code before changing anything — understand the intended behavior, not just the failure.

4. **Root-cause, don't patch symptoms.** Prefer fixing the actual defect over adding a try/catch or fallback that hides it. If a real workaround is unavoidable (e.g. a known upstream Magento/Windows bug), say so explicitly in the fix and explain why a direct fix isn't possible.
   - If the root cause traces into `vendor/` (Magento core or a third-party package), do not edit it directly. Fix it from `app/code/` instead — a plugin, a preference, or (for front-end asset/wiring issues) a `requirejs-config.js` mixin, matching this project's `magento-module.md` rule (plugins/observers over rewrites).

5. **Fix.** Make the minimal correct change. Don't refactor unrelated code while fixing a bug. Follow the project's existing coding rules (`.claude/rules/*.md`) for whatever file type you're touching.

6. **Verify.** Re-run whatever reproduced the bug in step 2 and confirm it's resolved. If it's a caching/static-asset-dependent fix (layout, ui_component, requirejs-config), flush the relevant cache and/or redeploy static content before verifying — a stale cache will make a real fix look like it didn't work. Also run the relevant test suite to check for regressions.

7. **Log it.** If the bug came from `BUGS.md`, move the entry from **Open** to **Fixed**, filling in Root Cause / Fix / Verified. If it was reported inline and `BUGS.md` exists, add a completed entry there too, so there's one place tracking what's been fixed.

## Output format

- **Bug**: one-sentence restatement of the symptom
- **Root cause**: what's actually wrong and why
- **Fix**: file(s) changed and what changed
- **Verification**: how you confirmed it's fixed (command run + result)
- **Regression check**: what else you tested to make sure nothing else broke

## Escalate instead of guessing

If after step 2–3 the root cause still isn't clear, or the fix would touch `vendor/` with no safe `app/code/` alternative, or the bug involves data corruption or a security vulnerability — say so explicitly rather than applying a speculative fix. Ask for the specific missing information (browser console output, a stack trace, exact reproduction steps) instead of guessing again.
