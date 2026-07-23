#!/usr/bin/env bash
# cavemen — lean-markdown procedure
#
# PreToolUse (Read|Write|Edit): if the target file is markdown, print a short
#   reminder to keep it lean and check the ledger before re-reading in full.
# PostToolUse (Write|Edit): if the target file is markdown, record/refresh a
#   one-line entry in the context ledger so future turns can reuse it instead
#   of repeating it.
#
# Always exits 0 — this is advisory only, it never blocks a tool call.

set -uo pipefail

MODE="${1:-pre}"
INPUT=$(cat)

FILE_PATH=$(node -e "
let d='';
process.stdin.on('data', c => d += c);
process.stdin.on('end', () => {
  try {
    const j = JSON.parse(d);
    const ti = j.tool_input || {};
    process.stdout.write(ti.file_path || ti.notebook_path || '');
  } catch (e) {}
});
" <<<"$INPUT" 2>/dev/null || echo "")

[ -z "$FILE_PATH" ] && exit 0

case "$FILE_PATH" in
  *.md|*.MD|*.markdown) ;;
  *) exit 0 ;;
esac

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
LEDGER="$REPO_ROOT/.claude/cavemen-ledger.md"
BASENAME=$(basename "$FILE_PATH")

if [ "$MODE" = "pre" ]; then
  echo "[cavemen] lean procedure: minimize tokens, skip filler, check .claude/cavemen-ledger.md before re-reading '$BASENAME' in full."
  exit 0
fi

# ── post: record/refresh the ledger entry ─────────────────────────
mkdir -p "$(dirname "$LEDGER")"
if [ ! -f "$LEDGER" ]; then
  printf '# Cavemen Context Ledger\n\nAuto-maintained by the cavemen plugin. One line per markdown file touched — check here before re-reading a file in full.\n\n' > "$LEDGER"
fi

REL_PATH="$FILE_PATH"
case "$FILE_PATH" in
  "$REPO_ROOT"/*) REL_PATH="${FILE_PATH#"$REPO_ROOT"/}" ;;
esac

TS=$(date '+%Y-%m-%d %H:%M')
ENTRY="- \`$REL_PATH\` — last touched $TS"

if grep -qF "\`$REL_PATH\`" "$LEDGER" 2>/dev/null; then
  TMP=$(mktemp)
  grep -vF "\`$REL_PATH\`" "$LEDGER" > "$TMP"
  mv "$TMP" "$LEDGER"
fi
echo "$ENTRY" >> "$LEDGER"

echo "[cavemen] ledger updated: $REL_PATH"
exit 0
