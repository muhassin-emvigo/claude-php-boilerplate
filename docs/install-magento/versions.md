# Version Matrix (latest-known snapshot)

Snapshot date: 2026-07-29. Versions drift — before running an install, re-verify
with a web search ("latest Magento Open Source version", "Composer latest version")
rather than trusting this file blindly. Update this table when you do.

| Component | Latest known | Notes |
|---|---|---|
| Magento Open Source | 2.4.9 (released 2026-05-12) | EOL 2029-05-12. This repo's `install-magento.sh` currently pins `2.4.7-p8` — see "Pinned vs latest" below. |
| PHP | 8.4 / 8.5 supported | 8.3 supported for upgrade path only, not new installs. Repo's `CLAUDE.md` baseline is PHP 8.2+; Magento 2.4.9 wants 8.4+. |
| Composer | 2.10.2 | 2.9+ required by Magento 2.4.9. Composer 2.2 is the LTS line if you need older-PHP compatibility. |
| MySQL | 8.4 | MariaDB 10.6+ also supported (project's stated DB baseline). |
| Search engine | OpenSearch 3 | OpenSearch 2.11 also works (used by this repo's non-Docker setup). Elasticsearch is no longer supported by recent Magento versions. |
| Cache / session | Redis 7.x, or Valkey 9 | Repo's Docker compose uses Redis. |
| Message queue (optional) | RabbitMQ 4.2 | Only needed if you enable async/message-queue features. |

## Pinned vs latest

This repo has two install paths, and they don't currently agree on a Magento version:

- `install-magento.sh` (Docker path) hardcodes `magento/product-community-edition: 2.4.7-p8` in the `composer.json` it writes.
- `install-magento-xampp.ps1` (native Windows path) installs whatever `composer.json` / `composer create-project` resolves to, which defaults to latest unless pinned.

If you want "latest" (2.4.9), either:
1. Edit the pinned version string in `scripts/install-magento.sh` (or root copy) before running it, or
2. Use the native XAMPP path and let Composer resolve latest, then confirm the resolved version with `bin/magento --version` after install.

Before committing to latest, check the module's own composer constraints in this repo (`composer.json` `require` block) still allow it — a jump from 2.4.7 to 2.4.9 can carry breaking changes for third-party modules.
