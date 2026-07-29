#!/usr/bin/env bash
# install-magento.sh
#
# One-command, non-interactive install of full Magento 2 core into this
# project (alongside your app/code module), wired to Docker services in
# docker/docker-compose.yml.
#
# Run from the project root, in Git Bash:
#   bash install-magento.sh
#
# Prerequisites:
#   - Docker Desktop installed and running
#   - Magento Marketplace Public/Private keys in docker/.env
#
# First run commonly takes 10-30+ minutes (Composer downloads Magento core).

set -euo pipefail

say() { echo -e "\n=== $1 ===\n"; }

command -v docker >/dev/null 2>&1 || {
    echo "Docker not found. Install Docker Desktop: https://www.docker.com/products/docker-desktop/"
    exit 1
}

if [ ! -f docker/.env ]; then
    say "Creating docker/.env from example"
    cp docker/.env.docker.example docker/.env
fi

if ! grep -q "^MAGENTO_PUBLIC_KEY=" docker/.env 2>/dev/null; then
    {
        echo ""
        echo "# Magento Marketplace auth keys (Access Keys in your Adobe account)"
        echo "MAGENTO_PUBLIC_KEY="
        echo "MAGENTO_PRIVATE_KEY="
    } >> docker/.env
fi

PUB_KEY=$(grep "^MAGENTO_PUBLIC_KEY=" docker/.env | cut -d= -f2- | tr -d '\r')
PRIV_KEY=$(grep "^MAGENTO_PRIVATE_KEY=" docker/.env | cut -d= -f2- | tr -d '\r')

if [ -z "$PUB_KEY" ] || [ -z "$PRIV_KEY" ]; then
    echo ""
    echo "MAGENTO_PUBLIC_KEY / MAGENTO_PRIVATE_KEY are empty in docker/.env."
    echo "Open docker/.env, paste your keys, then re-run:"
    echo "  bash install-magento.sh"
    exit 1
fi

# Detect module under app/code (skip placeholder Vendor/ModuleName if present)
MODULE_DIR=""
while IFS= read -r dir; do
    MODULE_DIR="$dir"
    break
done < <(find app/code -mindepth 2 -maxdepth 2 -type d 2>/dev/null | grep -v "Vendor/ModuleName" || true)

if [ -z "$MODULE_DIR" ]; then
    echo "No module found under app/code/. Run 'make init' first."
    exit 1
fi

VENDOR_NAME=$(basename "$(dirname "$MODULE_DIR")")
MODULE_NAME=$(basename "$MODULE_DIR")
MODULE_ID="${VENDOR_NAME}_${MODULE_NAME}"
say "Detected module: $MODULE_ID ($MODULE_DIR)"

NGINX_PORT=$(grep "^NGINX_PORT=" docker/.env | cut -d= -f2- | tr -d '\r')
NGINX_PORT=${NGINX_PORT:-8080}
BASE_URL="http://localhost:${NGINX_PORT}/"

if ! grep -q "magento/product-community-edition" composer.json; then
    say "Adding magento/product-community-edition to composer.json"
    php -r '
        $f = "composer.json";
        $j = json_decode(file_get_contents($f), true);
        $j["repositories"] = $j["repositories"] ?? [];
        $j["repositories"][] = ["type" => "composer", "url" => "https://repo.magento.com/"];
        $j["require"]["magento/product-community-edition"] = "2.4.7-p8";
        file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    '
fi

cd docker

say "Starting MySQL / Redis / OpenSearch / Mailhog"
docker compose up -d db redis opensearch mailhog

say "Waiting for MySQL"
until docker compose exec -T db mysqladmin ping -h 127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD:-root}" --silent >/dev/null 2>&1; do
    printf '.'
    sleep 3
done
echo " ready."

say "Building PHP image (Magento extensions + Composer)"
docker compose build php
docker compose up -d php nginx

say "Installing Magento core via Composer (slow step)"
docker compose exec -T php composer update --no-interaction

if [ ! -f ../bin/magento ]; then
    say "Running bin/magento setup:install"
    docker compose exec -T php bin/magento setup:install \
        --base-url="${BASE_URL}" \
        --db-host=db \
        --db-name=magento \
        --db-user=magento \
        --db-password=magento \
        --admin-firstname=Admin \
        --admin-lastname=User \
        --admin-email=admin@example.com \
        --admin-user=admin \
        --admin-password=Admin123! \
        --backend-frontname=admin \
        --language=en_US \
        --currency=USD \
        --timezone=America/Chicago \
        --use-rewrites=1 \
        --search-engine=opensearch \
        --opensearch-host=opensearch \
        --opensearch-port=9200 \
        --session-save=redis \
        --session-save-redis-host=redis \
        --cache-backend=redis \
        --cache-backend-redis-server=redis \
        --page-cache=redis \
        --page-cache-redis-server=redis \
        --no-interaction
else
    say "Magento already installed (bin/magento exists) — running setup:upgrade"
    docker compose exec -T php bin/magento setup:upgrade --no-interaction
fi

say "Enabling $MODULE_ID and finishing setup"
docker compose exec -T php bin/magento module:enable "$MODULE_ID" || true
docker compose exec -T php bin/magento setup:upgrade --no-interaction
docker compose exec -T php bin/magento setup:di:compile
docker compose exec -T php bin/magento setup:static-content:deploy -f en_US
docker compose exec -T php bin/magento deploy:mode:set developer
docker compose exec -T php bin/magento cache:flush
docker compose exec -T php bin/magento indexer:reindex

say "Done"
echo "Storefront : ${BASE_URL}"
echo "Admin      : ${BASE_URL}admin   (user: admin / pass: Admin123!)"
echo "Mailhog UI : http://localhost:8025"
echo ""
echo "Note: default NGINX_PORT is 8080 to avoid conflict with XAMPP on port 80."
