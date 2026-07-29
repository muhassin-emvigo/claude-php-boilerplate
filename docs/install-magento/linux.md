# Linux — Step by Step

No Linux-specific script exists in this repo. Two options:

- **Docker (recommended, OS-agnostic)**: install Docker Engine + Compose
  plugin, then follow [../00-first-time-setup.md](../00-first-time-setup.md)
  and run `bash install-magento.sh`. Skip everything below.
- **Native (apt/dnf)**: follow this doc.

Prereq: read [planner.md](planner.md) Step 0 first.

## Stage 1 — PHP

Debian/Ubuntu (via Ondřej Surý's PPA, since distro repos lag):

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.4 php8.4-{bcmath,curl,gd,intl,mbstring,mysql,soap,xsl,zip,sodium}
php -v
```

RHEL/Fedora (via Remi repo):

```bash
sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module enable php:remi-8.4
sudo dnf install php php-bcmath php-curl php-gd php-intl php-mbstring \
  php-mysqlnd php-soap php-xsl php-zip php-sodium php-opcache
```

8.2 is this project's stated minimum; use 8.4 if targeting latest Magento
(2.4.9).

## Stage 2 — Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version   # target 2.9+
```

Copy `auth.json.example` to `auth.json`, fill in Magento Marketplace
public/private keys.

## Stage 3 — Database

```bash
sudo apt install mysql-server   # or: mariadb-server
sudo systemctl start mysql
sudo mysql -e "CREATE DATABASE magento CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
```

## Stage 4 — Search engine

```bash
# OpenSearch (tarball or apt repo per opensearch.org docs)
curl -O https://artifacts.opensearch.org/releases/bundle/opensearch/<version>/opensearch-<version>-linux-x64.tar.gz
tar -xzf opensearch-*-linux-x64.tar.gz
cd opensearch-* && ./opensearch-tar-install.sh
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
