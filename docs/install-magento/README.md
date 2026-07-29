# Install Magento — Folder Guide

Agent-planning style install docs: identify OS/stack first, then follow a
step-by-step path through PHP, Composer, database, search engine, and
Magento itself. Complements the existing `install-magento.sh` (Docker,
OS-agnostic) and `scripts/install-magento-xampp.ps1` (Windows/XAMPP, already
in this repo) — this folder documents the equivalent manual steps for stacks
that don't have a script yet, and gives an agent a single entry point.

## Start here

[planner.md](planner.md) — OS identifier + version-display step, routes to
the right doc below.

## OS-specific steps

- [windows-xampp.md](windows-xampp.md) — this repo's default/tested stack
- [windows-wamp.md](windows-wamp.md)
- [macos.md](macos.md)
- [linux.md](linux.md)

## Reference

[versions.md](versions.md) — latest-known version matrix (Magento, PHP,
Composer, DB, search). Snapshot, not live — re-verify before relying on it.

## Related, existing docs

- [../00-first-time-setup.md](../00-first-time-setup.md) — this repo's
  existing XAMPP-focused setup walkthrough; `windows-xampp.md` here defers to
  it for the actual install script.
- `install-magento.sh` (repo root / `scripts/`) — Docker-based install, works
  the same on any OS.
- `scripts/install-magento-xampp.ps1` — native Windows/XAMPP installer.
