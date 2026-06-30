#!/usr/bin/env bash
# Claude PreToolUse hook — full pre-commit validation pipeline
#
#   [1/4] PHPCS            — blocks on coding standard violations
#   [2/4] PHPStan          — blocks on static analysis errors
#   [3/4] /security-review — blocks on injection, XSS, eval, secrets, traversal…
#   [4/4] /code-review     — blocks if verdict is "Request Changes"

set -euo pipefail

# ── Read JSON payload ─────────────────────────────────────────────
INPUT=$(cat)
COMMAND=$(echo "$INPUT" | python3 -c \
  "import sys,json; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('command',''))" \
  2>/dev/null || echo "")

if ! echo "$COMMAND" | grep -qE '(^|&&[[:space:]]*|;[[:space:]]*)git commit'; then
  exit 0
fi

# ── Anchor all paths to repo root ─────────────────────────────────
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
REVIEW_DIR="$REPO_ROOT/.reviews"
mkdir -p "$REVIEW_DIR"

# ── Colors ────────────────────────────────────────────────────────
CYAN='\033[0;36m';  GREEN='\033[0;32m';  RED='\033[0;31m'
YELLOW='\033[1;33m'; MAGENTA='\033[0;35m'; BLUE='\033[0;34m'
BOLD='\033[1m';  DIM='\033[2m';  NC='\033[0m'

# ── Helpers ───────────────────────────────────────────────────────
print_header() {
  echo ""
  echo -e "${CYAN}${BOLD}┌─────────────────────────────────────────────┐${NC}"
  echo -e "${CYAN}${BOLD}│  🔍  Pre-Commit Validation                  │${NC}"
  echo -e "${CYAN}${BOLD}└─────────────────────────────────────────────┘${NC}"
  echo ""
}
print_step()    { echo -e "${BOLD}  [${1}/4]  ${2}${NC}"; }
print_pass()    { echo -e "  ${GREEN}✔  ${1}${NC}"; }
print_fail()    { echo -e "  ${RED}✖  ${1}${NC}"; }
print_tip()     { echo -e "  ${YELLOW}⚠  ${1}${NC}"; }
print_divider() { echo -e "\n  ${DIM}────────────────────────────────────────────${NC}\n"; }

print_blocked() {
  echo -e "  ${RED}${BOLD}┌─────────────────────────────────────────────┐${NC}"
  echo -e "  ${RED}${BOLD}│  🚫  Commit Blocked                         │${NC}"
  echo -e "  ${RED}${BOLD}│  Fix the issues above, then try again.      │${NC}"
  echo -e "  ${RED}${BOLD}└─────────────────────────────────────────────┘${NC}\n"
}
print_approved() {
  echo -e "  ${GREEN}${BOLD}┌─────────────────────────────────────────────┐${NC}"
  echo -e "  ${GREEN}${BOLD}│  ✅  All checks passed — commit approved     │${NC}"
  echo -e "  ${GREEN}${BOLD}└─────────────────────────────────────────────┘${NC}\n"
}

# ── Findings table + stats line ───────────────────────────────────
# Claude emits: FINDING:<SEV>:<FILE>:<LINE>:<DESC>
render_findings_table() {
  local color="$1" header="$2" output="$3"
  local findings
  findings=$(echo "$output" | grep "^FINDING:" || true)
  [ -z "$findings" ] && return

  local C_SEV=10 C_FILE=22 C_LINE=6 C_DESC=33
  local bar_h bar_m bar_b
  bar_h="  ┌$(printf '─%.0s' $(seq 1 $((C_SEV+2))))┬$(printf '─%.0s' $(seq 1 $((C_FILE+2))))┬$(printf '─%.0s' $(seq 1 $((C_LINE+2))))┬$(printf '─%.0s' $(seq 1 $((C_DESC+2))))┐"
  bar_m="  ├$(printf '─%.0s' $(seq 1 $((C_SEV+2))))┼$(printf '─%.0s' $(seq 1 $((C_FILE+2))))┼$(printf '─%.0s' $(seq 1 $((C_LINE+2))))┼$(printf '─%.0s' $(seq 1 $((C_DESC+2))))┤"
  bar_b="  └$(printf '─%.0s' $(seq 1 $((C_SEV+2))))┴$(printf '─%.0s' $(seq 1 $((C_FILE+2))))┴$(printf '─%.0s' $(seq 1 $((C_LINE+2))))┴$(printf '─%.0s' $(seq 1 $((C_DESC+2))))┘"

  echo -e "\n  ${BOLD}${color}${header}${NC}"
  echo -e "${color}${bar_h}${NC}"
  printf "  ${color}│ %-${C_SEV}s │ %-${C_FILE}s │ %-${C_LINE}s │ %-${C_DESC}s │${NC}\n" \
    "Severity" "File" "Line" "Issue"
  echo -e "${color}${bar_m}${NC}"

  while IFS= read -r line; do
    local sev file lineno desc row_color
    sev=$(   echo "$line" | cut -d: -f2)
    file=$(  echo "$line" | cut -d: -f3)
    lineno=$(echo "$line" | cut -d: -f4)
    desc=$(  echo "$line" | cut -d: -f5-)
    file="${file:0:$C_FILE}"
    desc="${desc:0:$C_DESC}"
    case "$sev" in
      CRITICAL) row_color="$RED"    ;;
      HIGH)     row_color="$YELLOW" ;;
      MEDIUM)   row_color="$BLUE"   ;;
      *)        row_color="$DIM"    ;;
    esac
    printf "  ${row_color}│ %-${C_SEV}s │ %-${C_FILE}s │ %-${C_LINE}s │ %-${C_DESC}s │${NC}\n" \
      "$sev" "$file" "$lineno" "$desc"
  done <<< "$findings"

  echo -e "${color}${bar_b}${NC}"

  # ── Stats line ────────────────────────────────────────────────────
  local total critical high medium low
  total=$(   echo "$findings" | wc -l                                  | tr -d ' ')
  critical=$(echo "$findings" | grep "^FINDING:CRITICAL" | wc -l | tr -d ' ')
  high=$(    echo "$findings" | grep "^FINDING:HIGH"     | wc -l | tr -d ' ')
  medium=$(  echo "$findings" | grep "^FINDING:MEDIUM"   | wc -l | tr -d ' ')
  low=$(     echo "$findings" | grep -E "^FINDING:(LOW|NIT)" | wc -l | tr -d ' ')

  local plural=""; [ "$total" -ne 1 ] && plural="s"
  printf "\n  ${BOLD}%s finding%s${NC}  " "$total" "$plural"
  [ "$critical" -gt 0 ] \
    && printf "${RED}${BOLD}● %s critical${NC}  " "$critical" \
    || printf "${DIM}● 0 critical${NC}  "
  [ "$high" -gt 0 ] \
    && printf "${YELLOW}${BOLD}● %s high${NC}  " "$high" \
    || printf "${DIM}● 0 high${NC}  "
  [ "$medium" -gt 0 ] \
    && printf "${BLUE}${BOLD}● %s medium${NC}  " "$medium" \
    || printf "${DIM}● 0 medium${NC}  "
  [ "$low" -gt 0 ] \
    && printf "${DIM}● %s low/nit${NC}" "$low" \
    || printf "${DIM}● 0 low${NC}"
  echo -e "\n"
}

# ═════════════════════════════════════════════════════════════════
print_header

# ── [1/4] PHPCS ───────────────────────────────────────────────────
print_step "1" "PHPCS"
cd "$REPO_ROOT"
PHPCS_OUT=$(vendor/bin/phpcs --standard=phpcs.xml.dist 2>&1) && PHPCS_EXIT=0 || PHPCS_EXIT=$?
if [ $PHPCS_EXIT -ne 0 ]; then
  echo -e "\n${PHPCS_OUT}\n"
  print_fail "PHPCS failed — fix coding standards before committing."
  print_tip  "Run:  make lint-fix"
  echo ""; exit 2
fi
print_pass "PHPCS passed"
print_divider

# ── [2/4] PHPStan ─────────────────────────────────────────────────
print_step "2" "PHPStan"
PHPSTAN_OUT=$(vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress 2>&1) && PHPSTAN_EXIT=0 || PHPSTAN_EXIT=$?
if [ $PHPSTAN_EXIT -ne 0 ]; then
  echo -e "\n${PHPSTAN_OUT}\n"
  print_fail "PHPStan failed — static analysis issues found."
  print_tip  "Run:  make phpstan"
  echo ""; exit 2
fi
print_pass "PHPStan passed"
print_divider

# ── Shared: staged diff ────────────────────────────────────────────
CLAUDE_CMD="claude"
STAGED_DIFF=$(git diff --cached 2>/dev/null || echo "")
FILES_STAGED=$(git diff --cached --name-only | head -20)

if [ -z "$STAGED_DIFF" ]; then
  print_tip "No staged changes — skipping AI checks."; echo ""; exit 0
fi
STAGED_DIFF=$(echo "$STAGED_DIFF" | head -8000)

if ! command -v "$CLAUDE_CMD" &>/dev/null; then
  print_tip "Claude CLI not found — skipping AI checks."; echo ""; exit 0
fi

# ── Plugin availability probe ─────────────────────────────────────
PROBE="$REPO_ROOT/.claude/hooks/plugin-probe.sh"
STRICT="${CLAUDE_BOILERPLATE_STRICT:-0}"
probe_plugin() {
  [ -x "$PROBE" ] && bash "$PROBE" "$1" >/dev/null 2>&1 || true
}
plugin_missing_handler() {
  local name="$1" step="$2"
  if [ "$STRICT" = "1" ]; then
    print_fail "$name plugin not installed (STRICT mode)."
    echo -e "  ${DIM}Install: claude /plugins install https://claude.com/plugins/${name}${NC}"
    print_blocked; exit 2
  fi
  print_tip "$name plugin not installed — SKIPPING $step gate."
  echo -e "  ${DIM}Install: claude /plugins install https://claude.com/plugins/${name}${NC}"
  echo -e "  ${DIM}Set CLAUDE_BOILERPLATE_STRICT=1 to make this fail-hard.${NC}"
  print_divider
}

TIMESTAMP=$(date +%Y%m%d-%H%M%S)

# ── [3/4] Security Scan ───────────────────────────────────────────
print_step "3" "Security Scan  (security-guidance plugin)"
if ! probe_plugin "security-guidance"; then
  plugin_missing_handler "security-guidance" "security-review"
else
echo -e "  ${DIM}Scanning staged diff for dangerous patterns…${NC}\n"

SEC_PROMPT="/security-review

Scan the staged diff below for Magento/PHP security vulnerabilities across these categories:
SQL injection (raw queries), XSS (unescaped output in templates), CSRF (missing form keys),
Access Control (missing ACL in admin/api), path traversal, hardcoded secrets, ObjectManager usage.

### Files Staged
$FILES_STAGED

### Staged Diff
\`\`\`diff
$STAGED_DIFF
\`\`\`

## Output format (strictly follow this)

For every vulnerability found, output one line:
FINDING:<SEVERITY>:<FILE>:<LINE>:<DESCRIPTION_MAX_33_CHARS>

SEVERITY must be one of: CRITICAL / HIGH / MEDIUM / LOW

Then write a detailed section with code snippets and remediation for each finding.
End with exactly: SECURITY: Pass  OR  SECURITY: Fail"

SEC_FILE="$REVIEW_DIR/security-${TIMESTAMP}.md"
SEC_OUTPUT=$(echo "$SEC_PROMPT" | $CLAUDE_CMD --print 2>&1) || SEC_OUTPUT=""

if [ -n "$SEC_OUTPUT" ]; then
  { echo "# Security Scan Report"
    echo "**Date:** $(date '+%Y-%m-%d %H:%M:%S')"
    echo "**Files:** $FILES_STAGED"
    echo ""; echo "---"; echo ""
    echo "$SEC_OUTPUT"
  } > "$SEC_FILE"

  render_findings_table "$MAGENTA" "🛡️  Security Findings" "$SEC_OUTPUT"
  echo -e "  ${DIM}Full report → $SEC_FILE${NC}\n"

  if echo "$SEC_OUTPUT" | grep -qiE "SECURITY:[[:space:]]*Fail"; then
    print_fail "Security scan failed — vulnerabilities detected."
    print_blocked; exit 2
  fi
fi

print_pass "Security scan passed"
print_divider
fi

# ── [4/4] Code Review ─────────────────────────────────────────────
print_step "4" "Code Review  (code-review plugin)"
if ! probe_plugin "code-review"; then
  plugin_missing_handler "code-review" "code-review"
else
echo -e "  ${DIM}Running /code-review on staged diff…${NC}\n"

CR_PROMPT="/code-review

Review the staged changes for Magento 2 best practices, bugs, security issues, performance problems (e.g. N+1), and code quality.

### Files Staged
$FILES_STAGED

### Staged Diff
\`\`\`diff
$STAGED_DIFF
\`\`\`

## Output format (strictly follow this)

For every issue found, output one line:
FINDING:<TYPE>:<FILE>:<LINE>:<DESCRIPTION_MAX_33_CHARS>

TYPE must be one of: CRITICAL / HIGH / MEDIUM / LOW / NIT

Then write a detailed explanation section with context, impact, and suggested fix for each.
End with exactly: VERDICT: Approve  OR  VERDICT: Request Changes  OR  VERDICT: Needs Discussion"

CR_FILE="$REVIEW_DIR/review-${TIMESTAMP}.md"
CR_OUTPUT=$(echo "$CR_PROMPT" | $CLAUDE_CMD --print 2>&1) || CR_OUTPUT=""

if [ -n "$CR_OUTPUT" ]; then
  { echo "# Code Review Report"
    echo "**Date:** $(date '+%Y-%m-%d %H:%M:%S')"
    echo "**Files:** $FILES_STAGED"
    echo ""; echo "---"; echo ""
    echo "$CR_OUTPUT"
  } > "$CR_FILE"

  render_findings_table "$BLUE" "📋  Code Review Findings" "$CR_OUTPUT"
  echo -e "  ${DIM}Full report → $CR_FILE${NC}\n"

  if echo "$CR_OUTPUT" | grep -qiE "VERDICT:[[:space:]]*(Request Changes|Needs Discussion)"; then
    print_blocked; exit 2
  fi
  if echo "$CR_OUTPUT" | grep -qE "^FINDING:CRITICAL:"; then
    print_blocked; exit 2
  fi
fi

print_pass "Code review passed"
print_divider
fi
print_approved
exit 0
