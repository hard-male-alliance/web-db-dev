# Current architecture and decisions

Updated: 2026-10-19 (Asia/Singapore).
Status: Python infrastructure implemented; native Windows/Linux/macOS checks on five runner/architecture combinations and the Ubuntu Docker database/persistence/restore workflow passed. See the validation record for tested commits.

## 1. Final direction

**Demonstrate locally with PHP + Yii2 + MySQL/InnoDB. Docker Compose is the canonical reproduction path; project-local PHP on Windows, Linux and macOS is an optional editor/test convenience.**

The reviewed course material asks for a working demonstration and deployment/restoration documentation. No public-hosting requirement was identified. Confirm any additional verbal requirements separately. No Cloud SQL, Cloudflare, Durable Objects, Rust, or cloud deployment is required now, and no paid resources were provisioned.

Earlier cloud choices are preserved in [historical discussion](research/cloud-deployment-history.md), not active requirements. GitHub hosts source and CI; GitHub Pages cannot execute the PHP application and is unnecessary for this baseline.

## 2. Course and product scope

This is a team website, not an arbitrary database application:

- Team/member presentation and messages on the public site; information editing/publishing in administration.
- At least eleven meaningful database tables, documented database design, and each member contributing one file in every MVC layer.
- Naming/header conventions, SQL at `/data/install.sql`, team submissions at `/data/team/`, and submission download links.
- Requirements/design/implementation documents, user manual, deployment instructions, presentation with workload attribution, and a recorded walkthrough.
- Charts and one-click deployment are bonus items, not reasons to introduce distributed infrastructure.

See the [assignment summary](internet-database-development-assignments.md). The supplied 28-page L5-L8 PDF teaches Yii2 Advanced/MVC/Gii on pages 10-22 and template/layout/assets on pages 24-27. MySQL examples appear on pages 6 and 21. Examples alone do not prove an exclusive database restriction; the user chose MySQL for course alignment and reports language/framework restrictions.

Page 28 adds recorded walkthrough submission, omitted from the older summary. Its date field is blank; the older summary's November 30 has no confirmed year. Verify the actual deadline. The PDF is user-supplied and not stored in the repository. Removal of personal assignments from the local summary was editorial scope, not evidence that the instructor cancelled them.

Proposed product: a **team project archive** showing members, projects, collaboration, updates, messages, and documents. Exact features and schema remain open. Initial journey: login -> edit profile -> publish project/update -> visitor message -> moderation/reply -> document download. Infrastructure probe tables do not count toward course-domain tables.

## 3. Selected toolchain

| Component | Selection | Status |
| --- | --- | --- |
| PHP | 8.4.26 | Native runtime exercised; container image pinned |
| Composer | 2.10.3 | Native version/hash verified; container image pinned |
| Yii2 | 2.0.55 | Dependency locked; Advanced scaffold remains next work |
| Rendering | PHP views + CSS + selected JavaScript | No independent SPA needed |
| Database | MySQL 8.4.11, InnoDB, `pdo_mysql` | Image pinned; native server not installed |
| Orchestration | Compose 2.24+ | Configuration parsed with 5.6.0; engine absent locally |
| Source/checks | GitHub + GitHub Actions | No cloud deployment or production secrets |

`composer.lock` fixes packages; `containers.lock.json` records image-index digests and the Debian package snapshot; `toolchain.lock.json` pins native platform downloads. Literal image references live in Dockerfile/Compose. OS/kernel/Docker remain prerequisites, not a hermetic host build.

PHP renders HTML on the server. Yii2 Advanced's `frontend` and `backend` mean public/admin applications, both server-side PHP, not a browser/API split.

## 4. Infrastructure boundary

```text
Browser on demo machine
        |
        | 127.0.0.1:8080
        v
app: PHP + Yii2 + infrastructure probe
        |
        | private Compose network, application DB account
        v
db: MySQL + InnoDB
        |
        v
Project-scoped Docker volume
```

The current probe queries MySQL and renders HTML with Yii2; database failure produces 503. It is not the final MVC business application. A future Advanced scaffold replaces the probe entrypoint without changing the storage boundary.

- Only HTTP is published, on loopback; no host MySQL port.
- Explicit local-only default credentials; optional ignored `.env`. Changing initialization variables does not rotate an existing database's users.
- Non-root read-only app with temporary `/tmp`, dropped capabilities, and no host dependency/cache bind mounts.
- Dependencies installed inside the image, not copied from Windows `vendor/`. Repository metadata, secrets and caches are excluded from build context.
- Database health checks query using the initialized application account. App health checks exercise both HTTP and database access.
- Normal shutdown preserves the volume; volume deletion is explicit/destructive. No database scale-to-zero machinery.
- Built-in PHP server is for local development/demo only; public hosting needs a separate server/security design.

## 5. Reproduction and data contract

Start with `docker compose up --build --wait --wait-timeout 240`, or `scripts/dev.ps1 up` for startup plus checks. [README](../README.md) covers startup, ports, logs, persistence checks, export/import, cleanup, and native PHP.

Native runtimes live under `.phpenv/`; download caches and host-side backups stay under `.cache/`; `.temp/` is available for temporary work. Docker owns its image/container/volume state, isolated by Compose project identity. No global PHP/MySQL installation or cloud account is needed. Rebuild the app image after source changes.

A volume is not a backup. Export an InnoDB single-transaction SQL dump and test restoration into an empty database. Copy bytes with Docker rather than depending on PowerShell text encoding. Final `/data/install.sql` is an intentional reviewed hand-in, not an automatic commit of local data. Upload/session persistence will be chosen alongside those application features.

## 6. Verification status

Implemented checks:

1. Versions, PHP extensions, Composer metadata/platform requirements, Yii model validation.
2. Native HTTP -> PHP -> Yii2 view and untrusted-text escaping.
3. Compose-based real MySQL rollback/commit, foreign keys, schema discovery, utf8mb4, and HTTP readiness.
4. Marker survival across container replacement.
5. SQL export restored into a fresh CI database volume.

(1)-(2) passed on native Windows. All container checks (1), (3)-(5), image build, dependency audit, and HTTP readiness passed on an Ubuntu 24.04 GitHub-hosted runner at commit `1693e0434da26dcba32b148ca1227208e1d9b9ff`. See the [validation record](validation/local-environment.md). The Windows host still has no Docker engine; Docker Desktop, arm64, and a second team member's environment are not yet verified.

The completed website must additionally pass actual login/edit/publish/message/moderation/download and restore journeys, including reproduction on another member's machine.

## 7. Alternatives and next decisions

D1 helper transport is official, but audited PDO wrappers do not transparently preserve Yii2 transactions; see [D1 research](research/yii2-d1-feasibility.md). Managed MySQL, including Aiven/Cloud SQL, remains viable if public hosting becomes necessary; see [database research](research/managed-mysql-options.md). PostgreSQL/SQLite are not inherently unsuitable; MySQL was chosen for course/tooling alignment. Rust/DO/Containers are deferred, not rejected.

Next: reproduce on a team member's Docker host, scaffold Yii2 Advanced, define team identity/schema/features, choose a theme, implement authorization, decide uploads/sessions, and confirm the deadline. Build a vertical business workflow rather than expanding infrastructure further.

## 8. Python infrastructure and native environment

Host orchestration now uses Python 3.12+ standard library only. Root `setup.py` is
a project initializer, not a Python packaging entry point. Shell scripts contain
activation or backwards-compatible forwarding; Docker-internal MySQL helpers
remain POSIX shell because they execute within the Linux database image.

The disposable `.phpenv` owns PHP, Composer, INI and certificates. Activation
follows the practical venv model: prepend its bin directory, select its configuration
and restore shell state on deactivation. Dependencies remain in project `vendor/`,
preserving application paths. This does not provide OS sandboxing.

All platforms download binary releases. Requiring Unix users to build PHP merely
because PHP itself does not publish their binaries adds unnecessary friction.
Swoole build-static-php v1.18.1 supplies Linux/macOS x64/arm64 with PDO MySQL;
Windows retains the official NTS archive. NativePHP binaries were considered but
their published extension list lacks PDO MySQL. Unix PHP is pinned to 8.4.23;
Windows/Docker remain at 8.4.26. Composer accepts 8.4.23 through 8.4.x. Tests check
the actual platform pin rather than claiming identical builds across producers.

Downloads use HTTPS and locked SHA-256 hashes. Extraction rejects path escapes
and links. Runtime preparation is staged under `.temp`, validated at its final
location and rolled back on failure; a setup lock prevents competing writes.
Existing database volume and restore-confirmation contracts remain unchanged.
These are bounded safeguards, not a policy against third-party binary producers.

References:
- [Python venv](https://docs.python.org/3/library/venv.html)
- [Swoole binary release](https://github.com/swoole/build-static-php/releases/tag/v1.18.1)
- [NativePHP extensions](https://github.com/NativePHP/php-bin/blob/e0c212d36422e5aa1f7357ec0524d99de8ee79bf/php-extensions.txt)
- [GitHub runner selection](https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/choose-the-runner-for-a-job)

## References

- [Yii2 Advanced structure](https://www.yiiframework.com/extension/yiisoft/yii2-app-advanced/doc/guide/2.0/en/structure-applications)
- [Yii2 database access](https://www.yiiframework.com/doc/guide/2.0/en/db-dao)
- [Compose readiness ordering](https://docs.docker.com/compose/how-tos/startup-order/)
- [Compose services](https://docs.docker.com/reference/compose-file/services/)
- [Official PHP image](https://hub.docker.com/_/php)
- [Official MySQL image](https://hub.docker.com/_/mysql)
