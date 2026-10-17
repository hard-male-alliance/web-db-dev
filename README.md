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

`containers.lock.json` records the literal references used in the Dockerfile/Compose file. The pinned indexes include Linux amd64 and arm64. Neither a full Docker build nor execution on those architectures has been verified on the current Windows host, which has no Docker engine. Host Docker/Compose/kernel versions are prerequisites, not a repository-managed hermetic OS.

## Start

From the repository root:

```sh
docker compose up --build --wait --wait-timeout 240
```

Open **http://127.0.0.1:8080/**. The page reports PHP, Yii2, MySQL, and InnoDB after querying the database. Database failure returns HTTP 503. Both services have readiness checks.

PowerShell convenience commands (startup also runs checks):

```powershell
.\scripts\dev.ps1 up
.\scripts\dev.ps1 check
```

Optionally copy `.env.example` to `.env` and change `APP_PORT` if port 8080 is occupied. Defaults work without `.env`. Example/default passwords are **local-demo-only**, not production secrets. HTTP binds only to loopback and MySQL has no published host port. Do not expose this configuration to a public/shared network.

## Validate real behavior

```sh
docker compose exec -T app composer validate --strict --no-check-all
docker compose exec -T app composer check-platform-reqs
docker compose exec -T app php tests/toolchain.php
docker compose exec -T app php tests/database.php
```

The database test checks the actual MySQL version, Yii2 transactions and rollback, foreign keys, schema discovery, and Chinese/emoji round trips. Uniquely named probe tables are cleaned up afterward. These are real database checks, not mocks; they await a running Docker host.

```powershell
.\scripts\dev.ps1 persistence
```

This writes a marker, replaces both containers while preserving the volume, then verifies the marker. `_infrastructure_probe` is infrastructure state, not one of the required domain tables.

The GitHub Actions workflow additionally exports SQL and restores it into a fresh database volume. The workflow is defined, not claimed as already executed.

## Data, stop, and cleanup

```sh
docker compose logs --tail 100 app db
docker compose down
```

Normal `down` removes containers/network but **keeps the database volume**. MySQL data stays in Docker-managed project-scoped storage, not a host MySQL installation. Set `COMPOSE_PROJECT_NAME` and a different `APP_PORT` for simultaneous independent checkouts.

The app uses a non-root user, read-only root filesystem, and temporary `/tmp`. Dependencies are installed inside the image. Host `vendor/`, `.cache/`, `.env`, `.git/`, and documents are excluded from the build context. Source is copied, so rebuild after editing it.

**Destructive reset:** `docker compose down --volumes` permanently deletes this project's database volume. Export first; never use it for routine shutdown. MySQL initialization variables only apply to an empty volume; editing `.env` does not rotate existing accounts/passwords.

## SQL export and restore

PowerShell helpers preserve SQL bytes using `docker compose cp`, avoiding text-redirection encoding problems:

```powershell
.\scripts\dev.ps1 export
# Default output: .cache/exports/install.sql
.\scripts\dev.ps1 restore -Path .cache/exports/install.sql -ConfirmRestore
```

Import may overwrite tables/data. Review the dump and target first; the wrapper does not make a backup automatically. Validate restoration into a separate/empty database. The export assumes InnoDB tables without concurrent schema changes; it does not recreate accounts/server configuration or include routines/events by default.

For course hand-in, deliberately export using `-Path data/install.sql`, review the result for personal data/secrets, then include it with the submission. Routine backups stay ignored under `.cache/`.

Portable Docker commands (create the host `.cache/exports` directory first):

```sh
docker compose exec -T db sh /opt/project-tools/export.sh
docker compose cp db:/tmp/team-export.sql .cache/exports/install.sql
# Destructive import into the selected database, only after reviewing it:
docker compose cp .cache/exports/install.sql db:/tmp/team-import.sql
docker compose exec -T db sh /opt/project-tools/restore.sh
```

## Optional native Windows PHP

For editor support and PHP-only checks without Docker:

```powershell
.\scripts\bootstrap.ps1
.\scripts\composer.ps1 install --no-interaction --prefer-dist
.\scripts\check.ps1
```

This downloads pinned PHP/Composer/CA artifacts into `.cache/`, checks SHA-256, and uses project-local wrappers without changing global PATH. Windows x64 and Microsoft Visual C++ 2015-2022 x64 runtime are prerequisites. It does **not** install MySQL. `.php-version` is a hint, not a version switcher; Composer's `config.platform.php` affects dependency resolution, not the real runtime.

Native `check.ps1` verifies extensions, Yii2 model validation, and a temporary loopback HTTP rendering/escaping fixture, not full database behavior. Native `vendor/` is ignored and never copied into the Docker image.

## Upgrade discipline

Routine reproduction uses `composer install`, not `update`. Change versions deliberately and keep `.php-version`, `toolchain.lock.json`, Composer requirements/lock, `containers.lock.json`, Dockerfile and Compose references aligned. Refresh snapshot/CA pins intentionally. Run runtime, database, HTTP, persistence, and fresh-restore checks after updates; pinning does not replace security maintenance. Never disable TLS/hash validation to fix a download.

## Documentation and next work

- [Current architecture and decisions](docs/architecture-and-decisions.md)
- [Course assignment summary](docs/internet-database-development-assignments.md)
- [Historical cloud discussion](docs/research/cloud-deployment-history.md)
- [D1 investigation](docs/research/yii2-d1-feasibility.md)
- [Managed MySQL investigation](docs/research/managed-mysql-options.md)

Next: Yii2 Advanced scaffolding, the business schema, authentication/authorization, and the team website. Upload/session persistence will be decided with those features. PHP's built-in server is intentional for local demo/development, not production hosting.
