# 星河工坊 · Team project archive

**A working server-rendered team website using PHP + Yii2 + MySQL.** Public pages show members, projects, updates, moderated messages, live project charts and six downloadable course submissions. The administration app supports team/member editing, own-profile editing, project/update publishing, moderation/replies and audit history. No cloud account or paid service is required.

The repository uses Yii2 Advanced-style `common`, `frontend`, and `backend` applications with a single local HTTP router. Course materials are in `data/team/`; the fourteen-table real database export is `data/install.sql` (repeat-safe startup uses `data/schema.sql`).

> Demo identities, student IDs and workload attribution are explicitly illustrative. Replace `DEMO01/02/03` and confirm actual member contributions before course hand-in. Contacting the teaching assistant and emailing the submission are human actions, not performed by the application.

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

Open **http://127.0.0.1:8080/**; administration is **http://127.0.0.1:8080/admin/**. Startup installs missing schema and seeds only an entirely empty application database. Rebuild/restart never resets edited content or passwords. Both services have database-backed readiness checks (`/health` for HTTP).

| Demo login | Password | Permissions |
| --- | --- | --- |
| `admin` | `admin123456` | Team/members, all projects/updates, moderation/replies, audit history |
| `member` | `member123456` | Own profile, assigned/own-created projects and own updates |

These known demonstration credentials must not be used for public deployment. Login is available through the top navigation. Public visitors cannot see drafts, pending/rejected messages or private email addresses.

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

The app uses a non-root user, read-only root filesystem, and temporary `/tmp`. Dependencies are installed inside the image. Host `vendor/`, `.cache/`, `.env`, `.git/`, and research documents are excluded from the build context; the reviewed `data/team/` PDFs are included for downloads. Source is copied, so rebuild after editing it.

**Destructive reset:** `docker compose down --volumes` permanently deletes this project's database volume. Export first; never use it for routine shutdown. MySQL initialization variables only apply to an empty volume; editing `.env` does not rotate existing accounts/passwords.

## SQL export and restore

Python helpers preserve SQL bytes using `docker compose cp`, avoiding text-redirection encoding problems:

```sh
python scripts/dev.py export
# Default output: .cache/exports/install.sql
python scripts/dev.py restore --path .cache/exports/install.sql --confirm-restore
```

Import may overwrite tables/data. Review the dump and target first; the wrapper does not make a backup automatically. Validate restoration into a separate/empty database. The export assumes InnoDB tables without concurrent schema changes; it does not recreate accounts/server configuration or include routines/events by default.

`data/install.sql` is a real MySQL 8.4.11 dump of a pristine seeded demonstration database, supplied for course hand-in. It contains `DROP TABLE` statements: restore only into a dedicated empty database, after reviewing/backing up the target. Safe repeated startup instead uses `scripts/install.php` + `data/schema.sql`. Never replace the latter with a destructive dump. To refresh the hand-in dump, export a dedicated pristine demo database and review it for personal data/secrets. Routine backups stay ignored under `.cache/`.

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

## Native business application

With an existing MySQL 8.4 database/account, set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in the process environment and run:

```sh
python setup.py
python scripts/serve.py --port 8080
```

The server binds only to `127.0.0.1`; native runtime/session/log files stay in `.temp/runtime/`. It uses the same installer/router as Docker. The native command does not read `.env` (Compose does). Native MySQL installation is optional; Docker remains the canonical reproduction path.

Sessions are shared between frontend/backend within one instance and expire after one hour. Container replacement clears temporary sessions (users log in again), not database content. No user file-upload endpoint is exposed; submission downloads are restricted to reviewed local PDFs under `data/team/`.

## Application acceptance and course deliverables

```sh
# Run against a disposable demonstration database: this creates/edits test records.
python tests/application_journey.py --base-url http://127.0.0.1:8080
# Regenerate all six course PDFs from their source documents.
python scripts/build_submissions.py
```

The HTTP journey uses real cookies/CSRF tokens and MySQL-backed actions, not mocked controllers. See `docs/validation/application.md` for exact coverage and results. The PDF builder requires ReportLab (`pip install reportlab` in a suitable document-generation environment); the app itself does not depend on Python packages. See `docs/course/` for editable source documents, deployment/restoration instructions and explicit hand-in limitations.

A real browser walkthrough is included at `data/team/星河工坊_操作演示.webm`. To reproduce it against a disposable demo database, install Playwright/Chromium in your tooling environment and run `node scripts/record_walkthrough.cjs http://127.0.0.1:8080`. It creates a marked update and message, records publication/moderation/download, and checks desktop/mobile rendering plus browser errors. It is intentionally not a production-data test.

## Documentation

- [Current architecture and decisions](docs/architecture-and-decisions.md)
- [Course assignment summary](docs/internet-database-development-assignments.md)
- [Historical cloud discussion](docs/research/cloud-deployment-history.md)
- [D1 investigation](docs/research/yii2-d1-feasibility.md)
- [Managed MySQL investigation](docs/research/managed-mysql-options.md)

PHP's built-in server is intentional for local demo/development, not production hosting. Public hosting requires TLS, independent secrets/credentials, a production web server, retention/abuse policy and a separate deployment security review.
