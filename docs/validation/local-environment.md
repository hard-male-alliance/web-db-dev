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

Use the [README](../../README.md) for startup and SQL export/import. `scripts/dev.ps1 check` exercises the running environment; `scripts/dev.ps1 persistence` verifies volume retention across container replacement. Re-run the complete GitHub Actions workflow after infrastructure or dependency changes, especially the fresh-volume restore step.

This record is scoped to the tested commit, not a perpetual security or compatibility guarantee. Documentation-only updates following this run do not change the tested executable configuration.
