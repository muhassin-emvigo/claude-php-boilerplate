#!/usr/bin/env bash
# plugin-probe.sh — best-effort detection of a Claude Code plugin.
#
# Usage:
#   if bash .claude/hooks/plugin-probe.sh <plugin-name>; then
#     echo "installed"
#   else
#     echo "missing"
#   fi
#
# Strategy (each layer is a fallback for the one above):
#   1. `claude /plugins list` and grep for the plugin name
#   2. Common plugin directory locations under $HOME
#   3. Give up — return non-zero so callers degrade gracefully

set -uo pipefail

PLUGIN_NAME="${1:-}"
if [ -z "${PLUGIN_NAME}" ]; then
  echo "usage: plugin-probe.sh <plugin-name>" >&2
  exit 2
fi

# Layer 1: claude CLI plugin listing
if command -v claude >/dev/null 2>&1; then
  if claude /plugins list 2>/dev/null | grep -qi -E "(^|[/[:space:]])${PLUGIN_NAME}([[:space:]]|$)"; then
    exit 0
  fi
fi

# Layer 2: filesystem fallback
for dir in \
  "${HOME}/.claude/plugins" \
  "${HOME}/.config/claude/plugins" \
  "${HOME}/.config/claude-code/plugins" \
  "${HOME}/Library/Application Support/Claude/plugins" \
  "${CLAUDE_PLUGINS_DIR:-}"; do
  [ -z "${dir}" ] && continue
  if [ -d "${dir}/${PLUGIN_NAME}" ]; then
    exit 0
  fi
done

exit 1