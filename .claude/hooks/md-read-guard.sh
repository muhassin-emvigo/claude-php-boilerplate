#!/usr/bin/env bash
# Claude PreToolUse hook — blocks whole-file Read on large .md files.
#
# Rationale: .claude/rules/markdown.md tells Claude to Grep/Glob first and
# Read only the matched region. That's a soft instruction with nothing
# stopping a full-file Read. This hook makes it a hard gate: a Read on a
# .md file over the line threshold is blocked unless offset/limit is set
# (proof the call is scoped, e.g. after a Grep found the relevant lines).
#
# Small files (<= threshold) pass through — no point forcing Grep/Glob
# on a 20-line README.

set -euo pipefail

THRESHOLD=150

INPUT=$(cat)

PY=""
for cand in python3 py python; do
  if command -v "$cand" >/dev/null 2>&1 && "$cand" -c "" >/dev/null 2>&1; then
    PY="$cand"
    break
  fi
done

if [ -z "$PY" ]; then
  # No Python interpreter available — fail open, don't block tool use.
  exit 0
fi

read -r TOOL_NAME FILE_PATH OFFSET LIMIT <<EOF
$(echo "$INPUT" | "$PY" -c "
import sys, json
d = json.load(sys.stdin)
name = d.get('tool_name', '')
ti = d.get('tool_input', {}) or {}
print(name, ti.get('file_path', ''), ti.get('offset', ''), ti.get('limit', ''))
" 2>/dev/null || echo "  ")
EOF

if [ "$TOOL_NAME" != "Read" ]; then
  exit 0
fi

if [[ "$FILE_PATH" != *.md ]] && [[ "$FILE_PATH" != *.MD ]]; then
  exit 0
fi

# offset or limit present => scoped read, allowed.
if [ -n "$OFFSET" ] || [ -n "$LIMIT" ]; then
  exit 0
fi

if [ ! -f "$FILE_PATH" ]; then
  exit 0
fi

LINES=$(wc -l < "$FILE_PATH" 2>/dev/null || echo 0)
LINES=${LINES//[[:space:]]/}

if [ "$LINES" -le "$THRESHOLD" ]; then
  exit 0
fi

echo "Blocked: whole-file Read on '$FILE_PATH' ($LINES lines, threshold $THRESHOLD)." >&2
echo "Per .claude/rules/markdown.md: Grep/Glob first, then Read with offset/limit to pull only the matched region." >&2
echo "If the full file is genuinely needed (e.g. user asked to read it entirely), pass offset=1 and limit=$LINES to proceed." >&2
exit 2
