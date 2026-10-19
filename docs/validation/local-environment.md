# Local infrastructure validation

Date: 2026-10-17 (Asia/Singapore).

## Executed GitHub Actions run

- Repository: `hard-male-alliance/web-db-dev`.
- Workflow: `.github/workflows/local-environment.yml`, job `reproduce`.
- Tested code: `1693e0434da26dcba32b148ca1227208e1d9b9ff`.
- [Run 39046239319](https://github.com/hard-male-alliance/web-db-dev/actions/runs/39046239319), conclusion **success**.
- Runner: Ubuntu 24.04.5 LTS, runner image `20261012.334.1`.
- Job: 23:42:41-23:45:40 Singapore time, **2 min 59 sec**.
- No application cloud service, production secret, or paid database was provisioned. The workflow used an isolated project-scoped Compose stack on the hosted runner and removed its containers/volumes afterward.

## Results

| Check | Observed result |
| --- | --- |
| Compose configuration, pinned image downloads, PHP extension build, Composer install | Passed; complete stack became healthy |
| PHP 8.4.26 / Yii2 2.0.55, required extensions and model validation | Passed |
| Composer metadata, platform requirements, dependency vulnerability audit | Passed; no advisories reported at run time |
| Yii2 connection to actual MySQL 8.4.11 / InnoDB | Passed |
| Failed foreign-key write causes transaction rollback | Passed |
| Successful related writes commit | Passed |
| Primary/foreign-key schema discovery and Chinese/emoji round trip | Passed |
| HTTP landing page queries the database and renders HTML | Passed |
| Write marker -> Compose down -> recreate containers with retained volume -> verify marker | Passed |
| Export SQL -> delete this CI project's database volume -> initialize empty volume -> import SQL -> verify marker | Passed |
| Database semantics and HTTP after restoration | Passed |

Representative output:

```text
No security vulnerability advisories found.
PASS PHP 8.4.26, Yii2 2.0.55, required extensions and model validation.
PASS MySQL 8.4.11 / InnoDB, Yii2 transactions, foreign keys, schema discovery, utf8mb4.
PASS persistence marker survived container replacement.
```

The last marker assertion was run both after container replacement and after importing the dump into a fresh volume. This distinguishes real restoration from merely reading the original database again. Probe tables are not course-domain tables or completed business features.

## Local checks and limitations

Native Windows PHP/Composer checks, HTTP rendering/escaping, PHP/PowerShell/shell syntax, Compose 5.6.0 configuration parsing, version/digest consistency, and documentation links were checked locally. PowerShell wrapper safety/control-flow checks used a mocked Docker command; they are not evidence that Windows Docker works.

The host has no Docker Engine/Desktop, so container execution was verified remotely rather than on this Windows installation. arm64/macOS, a second team member's machine, the future Yii2 Advanced application, Gii itself, authentication, uploads, session persistence, and business workflows remain untested. A successful environment check does not establish those features.

## Reproduction

Use the [README](../../README.md) for startup and SQL export/import. `python scripts/dev.py check` exercises the running environment; `python scripts/dev.py persistence` verifies volume retention across container replacement. Re-run the complete GitHub Actions workflow after infrastructure or dependency changes, especially the fresh-volume restore step.

This record is scoped to the tested commit, not a perpetual security or compatibility guarantee. Documentation-only updates following this run do not change the tested executable configuration.

## Native Python infrastructure validation (2026-10-19)

Tested code: `f585e11a84216cd3256b5b981d0cfa16f3a4934b` on branch
`codex/python-phpenv`.

[PHP virtual environment run 39196288283](https://github.com/hard-male-alliance/web-db-dev/actions/runs/39196288283)
completed successfully on all five runners:

| Runner | Architecture | Downloaded PHP | Result |
| --- | --- | --- | --- |
| windows-2025 | x64 | Official NTS 8.4.26 | Passed |
| ubuntu-24.04 | x64 | Swoole static 8.4.23 | Passed |
| ubuntu-24.04-arm | arm64 | Swoole static 8.4.23 | Passed |
| macos-15-intel | x64 | Swoole static 8.4.23 | Passed |
| macos-15 | arm64 | Swoole static 8.4.23 | Passed |

Every job uses Python 3.12 and a fresh checkout under `project with spaces`, with
no pre-existing `.phpenv` or `vendor`. The actual journey is:

1. Run standard-library infrastructure tests before initializing PHP.
2. Download pinned binaries and run locked Composer installation/platform checks.
3. Repeat `python setup.py` to verify reuse.
4. Run 11 tests, including offline runtime reuse, failed-replacement rollback,
   archive path combinations, cache corruption, and restoration of shell variables.
5. Activate twice, resolve/run PHP and Composer, deactivate and verify PATH and
   configuration restoration. Test Bash on Unix, Zsh when installed, and PowerShell.
   Windows skips only the Unix-shell test.
6. Verify PHP/extension/Yii2 versions, validate a Yii2 model, and start a real local
   PHP server to render and escape untrusted HTML through a Yii2 view.
7. Execute the legacy PowerShell PHP and Composer entry points (historical check;
   those wrappers have since been removed in favor of the Python commands).

The first native run failed because the initializer required optional `pdo_sqlite`
from the old Windows extension list. All Unix runtime versions were already correct;
the application needs MySQL, not SQLite. The fix requires the application's extension
set, retains optional Windows SQLite loading, and compares extension names without
case sensitivity. No checksum or version checks were bypassed.

Local Windows reproduction also passed setup, repeated setup, the 11-test suite
(one Unix-shell skip), and the real HTTP check. Native checks do not run a MySQL
server; the separate Docker workflow remains responsible for database behavior.

[Docker/MySQL run 39196288224](https://github.com/hard-male-alliance/web-db-dev/actions/runs/39196288224)
also passed at the same tested commit in **2 min 55 sec** on Ubuntu 24.04. It ran
`python scripts/dev.py up` and `check`, then verified transaction/foreign-key/utf8mb4
behavior, HTTP readiness and persistence across container replacement. SQL export
and confirmed import used the new Python entry points, with restoration into a fresh
database volume followed by marker, database and HTTP checks. CI cleanup passed.

This extends the earlier validation without claiming Windows/macOS Docker execution
or completed business-application behavior. The native environment and full Docker
database journeys are complementary checks, not substitutes for each other.
