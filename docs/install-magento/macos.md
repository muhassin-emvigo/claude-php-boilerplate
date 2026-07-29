# macOS — Step by Step

No macOS-specific script exists in this repo. Two options:

- **Docker (recommended, OS-agnostic)**: install Docker Desktop, then follow
  [../00-first-time-setup.md](../00-first-time-setup.md) and run
  `bash install-magento.sh`. Skip everything below.
- **Native (Homebrew)**: follow this doc.

Prereq: read [planner.md](planner.md) Step 0 first.

## Stage 1 — PHP

```bash
brew install php@8.4   # or php@8.2 minimum; 8.4 for latest Magento 2.4.9
brew link php@8.4 --force
php -v
```

Enable extensions (Homebrew's PHP ships most Magento-required extensions by
default): bcmath, curl, gd, intl, mbstring, pdo_mysql, soap, sockets, sodium,
xsl, zip, openssl. Verify:

```bash
for ext in bcmath curl gd intl mbstring pdo_mysql soap sockets sodium xsl zip openssl; do
  php -m | grep -qi "^$ext$" || echo "MISSING: $ext"
done
```

## Stage 2 — Composer

```bash
brew install composer
composer --version   # target 2.9+
```

Copy `auth.json.example` to `auth.json`, fill in Magento Marketplace
public/private keys.

## Stage 3 — Database

```bash
brew install mysql       # or: brew install mariadb
brew services start mysql
mysql -uroot -e "CREATE DATABASE magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

## Stage 4 — Search engine

```bash
brew install opensearch
brew services start opensearch
curl -s localhost:9200
```

Use OpenSearch 3.x for latest Magento (2.4.9); 2.11 also works against older
pins like this repo's `2.4.7-p8`.

## Stage 5 — Magento install

```bash
composer install
php bin/magento setup:install \
  --base-url=http://localhost/ \
  --db-host=127.0.0.1 --db-name=magento --db-user=root --db-password= \
  --admin-firstname=Admin --admin-lastname=User \
  --admin-email=admin@example.com --admin-user=admin --admin-password=Admin123! \
  --backend-frontname=admin --language=en_US --currency=USD \
  --timezone=America/Chicago --use-rewrites=1 \
  --search-engine=opensearch --opensearch-host=localhost --opensearch-port=9200 \
  --no-interaction
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f en_US
php bin/magento deploy:mode:set developer
```

## Display versions after install

```bash
php -v
composer --version
php bin/magento --version
mysql --version
curl -s localhost:9200 | head -1
```
