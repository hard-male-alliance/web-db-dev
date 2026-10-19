# Team website: reproducible local infrastructure

**Current decision: local PHP + Yii2 + MySQL for the course demo.** No cloud account, paid database, Rust toolchain, or global PHP/MySQL installation is required. The current landing page verifies the infrastructure; it is not the finished website or Yii2 Advanced application.

## Prerequisites and pins

Install Docker Desktop in **Linux containers** mode (Windows/macOS), or Docker Engine with Compose on Linux, and start the engine. Compose **2.24.0+** is required; configuration parsing was checked with 5.6.0. First-time builds need internet access.

| Component | Pin |
| --- | --- |
| PHP | 8.4.26, official CLI Bookworm image + immutable digest |
| Composer | 2.10.3, official image + immutable digest |
| Yii2 | 2.0.55, exact requirement + `composer.lock` |
| MySQL / InnoDB | 8.4.11, official image + immutable digest |
| Extension build packages | Debian snapshot `20261001T000000Z` |

`containers.lock.json` records the literal references used in the Dockerfile/Compose file. The pinned indexes include Linux amd64 and arm64. The full build and integration/restore workflow passed on an Ubuntu 24.04 GitHub-hosted runner; arm64 and Docker Desktop execution are not yet tested. The current Windows host has no Docker engine. Host Docker/Compose/kernel versions are prerequisites, not a repository-managed hermetic OS.

## Start

From the repository root:

```sh
docker compose up --build --wait --wait-timeout 240
```

Open **http://127.0.0.1:8080/**. The page reports PHP, Yii2, MySQL, and InnoDB after querying the database. Database failure returns HTTP 503. Both services have readiness checks.

Cross-platform Python commands (startup also runs checks):

```sh
python scripts/dev.py up
python scripts/dev.py check
```

Optionally copy `.env.example` to `.env` and change `APP_PORT` if port 8080 is occupied. Defaults work without `.env`. Example/default passwords are **local-demo-only**, not production secrets. HTTP binds only to loopback and MySQL has no published host port. Do not expose this configuration to a public/shared network.

## Validate real behavior

```sh
docker compose exec -T app composer validate --strict --no-check-all
docker compose exec -T app composer check-platform-reqs
docker compose exec -T app php tests/toolchain.php
docker compose exec -T app php tests/database.php
```

The database test checks the actual MySQL version, Yii2 transactions and rollback, foreign keys, schema discovery, and Chinese/emoji round trips. Uniquely named probe tables are cleaned up afterward. These are real database checks, not mocks; they passed in GitHub Actions.

```sh
python scripts/dev.py persistence
```

This writes a marker, replaces both containers while preserving the volume, then verifies the marker. `_infrastructure_probe` is infrastructure state, not one of the required domain tables.

The GitHub Actions workflow additionally exports SQL and restores it into a fresh database volume. All steps passed in [run 39046239319](https://github.com/hard-male-alliance/web-db-dev/actions/runs/39046239319). See the [validation record](docs/validation/local-environment.md) for the tested commit and scope.

## Data, stop, and cleanup

```sh
docker compose logs --tail 100 app db
docker compose down
```

Normal `down` removes containers/network but **keeps the database volume**. MySQL data stays in Docker-managed project-scoped storage, not a host MySQL installation. Set `COMPOSE_PROJECT_NAME` and a different `APP_PORT` for simultaneous independent checkouts.

The app uses a non-root user, read-only root filesystem, and temporary `/tmp`. Dependencies are installed inside the image. Host `vendor/`, `.cache/`, `.env`, `.git/`, and documents are excluded from the build context. Source is copied, so rebuild after editing it.

**Destructive reset:** `docker compose down --volumes` permanently deletes this project's database volume. Export first; never use it for routine shutdown. MySQL initialization variables only apply to an empty volume; editing `.env` does not rotate existing accounts/passwords.

## SQL export and restore

Python helpers preserve SQL bytes using `docker compose cp`, avoiding text-redirection encoding problems:

```sh
python scripts/dev.py export
# Default output: .cache/exports/install.sql
python scripts/dev.py restore --path .cache/exports/install.sql --confirm-restore
```

Import may overwrite tables/data. Review the dump and target first; the wrapper does not make a backup automatically. Validate restoration into a separate/empty database. The export assumes InnoDB tables without concurrent schema changes; it does not recreate accounts/server configuration or include routines/events by default.

For course hand-in, deliberately export using `--path data/install.sql`, review the result for personal data/secrets, then include it with the submission. Routine backups stay ignored under `.cache/`.

Portable Docker commands (create the host `.cache/exports` directory first):

```sh
docker compose exec -T db sh /opt/project-tools/export.sh
docker compose cp db:/tmp/team-export.sql .cache/exports/install.sql
# Destructive import into the selected database, only after reviewing it:
docker compose cp .cache/exports/install.sql db:/tmp/team-import.sql
docker compose exec -T db sh /opt/project-tools/restore.sh
```

## Native PHP environment: Windows, Linux and macOS

The only initialization entry point is root `setup.py` on every platform.
Requires **Python 3.12+**, with no pip packages. Setup downloads precompiled PHP,
Composer and CA certificates; it never compiles PHP or installs a global runtime.
Windows x64 also requires the Microsoft Visual C++ 2015-2022 x64 runtime.
Linux/macOS support x64 and arm64. MySQL remains managed by Docker.

```sh
python setup.py
source ./activate.sh  # Bash or Zsh; source, do not execute
php --version
composer --version
python scripts/check.py
deactivate
```

PowerShell on any supported OS:

```powershell
python setup.py
. ./activate.ps1
php --version
composer --version
python scripts/check.py
deactivate
```

Activation changes only the current shell: PHP executable lookup, `PHPRC`, isolated
INI scanning, and project-local Composer home/cache. Repeated activation does not
stack PATH entries; `deactivate` restores previous values, including unset variables.
It does not start services or modify shell profiles. Plain POSIX `sh` is not supported.

Setup installs locked project dependencies by default. Use `python setup.py --skip-composer`
to provision just the runtime. Repeated setup reuses the environment and download cache;
failed runtime replacement restores the previous environment. If setup is forcibly
interrupted, remove the stale `.cache/setup.lock` directory before retrying. Stop
this environment's PHP processes before replacing its runtime.

`.phpenv/` contains `bin/php` (Windows: `bin/php.exe`), Composer launchers,
`composer.phar`, `php.ini`, `conf.d/`, certificates and a `ready.json` manifest.
It is disposable and ignored by Git, like `.venv`; recreate after moving a checkout.
Download caches stay under `.cache/`, staging under `.temp/`, and Yii2 dependencies
under the existing ignored `vendor/`. This is not an OS or filesystem sandbox.

| Platform | Binary source | PHP pin |
| --- | --- | --- |
| Windows x64 | Official windows.php.net NTS archive | 8.4.26 |
| Linux x64 / arm64 | Swoole build-static-php v1.18.1 | 8.4.23 |
| macOS x64 / arm64 | Swoole build-static-php v1.18.1 | 8.4.23 |
| Docker | Existing official PHP CLI image | 8.4.26 |

Each archive is SHA-256 checked; setup verifies the running PHP version and required
extensions. Unix packages are third-party precompiled binaries, not official PHP
binaries. Composer accepts `~8.4.23` (8.4.23 through 8.4.x); exact platform download
pins live in `toolchain.lock.json`. `.php-version` remains the Docker/Windows pin,
not a cross-platform version switcher. Native Linux binaries are statically linked;
macOS binaries still depend on system libraries.

Without activation:

```sh
python scripts/php.py tests/toolchain.php
python scripts/composer.py install --no-interaction --prefer-dist
python scripts/check.py
python scripts/dev.py up
python scripts/dev.py persistence
python scripts/dev.py export --path .cache/exports/install.sql
# Destructive import: review the dump and target first.
python scripts/dev.py restore --path .cache/exports/install.sql --confirm-restore
python scripts/dev.py down
python -m unittest discover -s tests -p 'test_*.py' -v
```

GitHub Actions `PHP virtual environment` tests clean/repeat setup, shell activation,
Composer, Yii2 and HTTP on Windows x64, Linux x64/arm64 and macOS x64/arm64, in
checkouts with spaces in their paths. `Local environment` retains Docker/MySQL
persistence and fresh-volume restoration and exercises the Python entry points.

The former PowerShell wrappers under `scripts/` have been removed. Use `setup.py`
for initialization and the Python commands above for ongoing tasks. Root
`activate.ps1` and `activate.sh` only activate an initialized environment.
Composer's `config.platform.php` affects dependency resolution, not the real runtime.
Native `check.py` checks extensions, Yii2 validation and HTTP rendering/escaping,
not full database behavior. Native `vendor/` is never copied into the Docker image.

## Upgrade discipline

Routine reproduction uses `composer install`, not `update`. Change versions deliberately and keep `.php-version`, `toolchain.lock.json`, Composer requirements/lock, `containers.lock.json`, Dockerfile and Compose references aligned. Refresh snapshot/CA pins intentionally. Run runtime, database, HTTP, persistence, and fresh-restore checks after updates; pinning does not replace security maintenance. Never disable TLS/hash validation to fix a download.

## Documentation and next work

- [Current architecture and decisions](docs/architecture-and-decisions.md)
- [Course assignment summary](docs/internet-database-development-assignments.md)
- [Historical cloud discussion](docs/research/cloud-deployment-history.md)
- [D1 investigation](docs/research/yii2-d1-feasibility.md)
- [Managed MySQL investigation](docs/research/managed-mysql-options.md)

Next: Yii2 Advanced scaffolding, the business schema, authentication/authorization, and the team website. Upload/session persistence will be decided with those features. PHP's built-in server is intentional for local demo/development, not production hosting.
