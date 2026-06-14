# TorStatus

Web application listing Tor nodes running at [https://torstatus.rueckgr.at/](https://torstatus.rueckgr.at/). Initially developed by Joseph B. Kowalski.

## Docker-based setup (recommended)

### Prerequisites

- Git
- [Composer](https://getcomposer.org/)
- Node.js/npm for the Vite/Tailwind frontend build
- Docker together with Docker Compose

### Steps

- Clone the repository.
- Install PHP dependencies with Composer: `cd nginx && composer install --no-dev`.
- Install and build frontend assets: `cd nginx && npm install && npm run build`.
- Create the Docker network `torstatus` using `docker network create torstatus`.
- Run `docker compose build` from the root of your repository clone.
- Run `docker compose up` to start everything.
- Wait for the updater to finish a full update cycle.
- Point your browser to [http://localhost:8765](http://localhost:8765).

### Things to note

- The first start-up takes more time because the database must be initialized.
- As long as the database is not up, the updater will log database connection errors. This is expected during startup.
- The first updater run after startup can take longer because the shared cache is empty.
- The committed CSS/JS assets are usable directly. After frontend changes run `cd nginx && npm run build` to rebuild the Vite/Tailwind bundle.
- The Tor process will warn that its control port is accessible from non-local addresses. In the default Compose setup it is only reachable from the TorStatus Docker network.
- All containers include health checks that you can inspect with `docker ps`.

### Environment variables

- `REAL_SERVER_IP`: The public IPv4 or IPv6 address of the TorStatus instance. Used for determining whether a Tor exit node will allow connecting to this TorStatus instance.
- `HIDDEN_SERVICE_URL`: Optional onion-service URL shown in the UI.

### Cache backend

By default the Docker config (`nginx/web/config_docker.php`) uses Valkey:

```php
$redis_uri = 'tcp://valkey:6379';
```

To use Memcached instead, change it to:

```php
$redis_uri = '';
$memcached_host = 'memcached';
```

The Compose file only starts `valkey`. To use Memcached instead, change `$redis_uri` to empty in `config_docker.php`.

### Public web root

Only `nginx/web/public` is intended to be reachable by the web server. Internal files such as `src`, `templates`, `config.php`, and `init.php` remain under `nginx/web` but outside the document root.

The included nginx configuration uses:

```nginx
root /var/www/html/public;
```

### Reverse proxy

If you run a reverse proxy in front of TorStatus, forward incoming requests either to nginx or directly to PHP-FPM.

#### Forward requests to nginx

The `nginx` container exposes port `8765`. You can forward requests there, for example in Apache with `mod_proxy`:

```apache
ProxyPass / http://127.0.0.1:8765/
ProxyPassReverse / http://127.0.0.1:8765/
```

#### Forward requests to PHP-FPM

The `php-fpm` container exposes port `9001`. Serve static files from `nginx/web/public` and forward PHP requests to PHP-FPM. Example Apache snippet:

```apache
DocumentRoot /path/to/torstatus/nginx/web/public

<FilesMatch ".+\.ph(ar|p|tml)$">
  ProxyFCGISetEnvIf "true" SCRIPT_FILENAME "/var/www/html/public%{reqenv:SCRIPT_NAME}"
  SetHandler "proxy:fcgi://127.0.0.1:9001"
</FilesMatch>
```

### Hidden services

- The directory `tor/hidden_services` is mounted at `/var/lib/tor/hidden_services` inside the Tor container.
- Place hidden-service key material there.
- Add a file to `tor/torrc.d` configuring the hidden service with `HiddenService*` directives. Use `/var/lib/tor/hidden_services/...` for `HiddenServiceDir`.

### Logging

All containers' logs are sent to journald.

## Setup without Docker

### Tor

- You need access to a running Tor daemon.
- Configure a control port with a password using `ControlPort` and `HashedControlPassword` in `torrc`.
- Set `UseMicrodescriptors` to `0` in `torrc`.

### Shared cache

Set up one of these cache backends:

- memcached
- Redis
- Valkey

Configure it in `config.php`:

```php
// To use Redis/Valkey:
$redis_uri = 'tcp://127.0.0.1:6379';

// To use Memcached instead (default when $redis_uri is empty):
$redis_uri = '';
$memcached_host = '127.0.0.1';
```

### MariaDB

Set up MariaDB, create a database with a user, and populate the database using [mariadb/sql/install.sql](mariadb/sql/install.sql).

#### Migrations

After the initial setup, apply any pending migrations using the CLI helper:

```bash
php nginx/web/apply_migration.php
```

The script tracks applied migrations in `nginx/web/.applied_migrations.json` (ignored by Git). To skip a migration that was already applied manually:

```bash
php nginx/web/apply_migration.php --skip 20250614_add_missing_indexes.sql
```

### Web application

- Copy [nginx/web/config_template.php](nginx/web/config_template.php) to `nginx/web/config.php` and modify it to your needs.
- Install PHP dependencies with Composer: `cd nginx && composer install --no-dev`.
- Build frontend assets: `cd nginx && npm install && npm run build`.
- Set up a web server with PHP support.
- Configure the web server document root to `nginx/web/public`.
- The frontend source lives in `nginx/web/assets/src` and is built with Vite/Tailwind into `nginx/web/public/css/app.css` and `nginx/web/public/js/app.js`.
- Install PHP modules: `mysqli`, `gd`, and `redis` (or `memcached` if you prefer Memcached).

### Updater

- `cd updater`.
- Run `uv run python -m torstatus_updater`.
- The updater reads the same `config.php` values as the PHP frontend.
- IPv6 OR addresses are read from Tor descriptors and stored in the `ORAddresses*` tables. Country lookup supports Tor's `geoip` and `geoip6` files when both are available.
