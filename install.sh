#!/bin/bash

set -e

cd "$(dirname "$0")"

echo "=== TorStatus dependency installer ==="

# Check PHP
if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: PHP is not installed. Please install PHP 8.3 or newer."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "Found PHP $PHP_VERSION"

# Check required PHP extensions
for ext in mysqli gd memcached; do
    if ! php -m | grep -qi "^$ext$"; then
        echo "WARNING: PHP extension '$ext' is not enabled."
    fi
done

# Install Composer if missing
if ! command -v composer >/dev/null 2>&1; then
    echo "Composer not found. Installing..."
    EXPECTED_CHECKSUM=$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM=$(php -r "echo hash_file('sha384', 'composer-setup.php');")
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        echo "ERROR: Composer installer checksum mismatch"
        rm composer-setup.php
        exit 1
    fi
    php composer-setup.php --quiet
    rm composer-setup.php
    mv composer.phar /usr/local/bin/composer
    echo "Composer installed."
else
    echo "Composer already installed."
fi

# Install PHP dependencies via Composer
echo "Installing PHP dependencies..."
composer install --no-dev --no-interaction

echo "=== Installation complete ==="
