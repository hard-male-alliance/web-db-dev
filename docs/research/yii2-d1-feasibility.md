# Yii2 to Cloudflare D1 feasibility

Date: 2026-10-17 (Asia/Singapore). Scope: course team website using PHP and Yii2 Advanced in Cloudflare Containers, with persistent data outside the container. This is a source/documentation investigation, not a deployed proof of concept.

**Historical project decision (now superseded by local Docker Compose):** the user selected Google Cloud SQL for MySQL rather than D1. This investigation remains the rationale and a reference for future reconsideration. No cloud database is needed for the current local-demo baseline. See [Architecture and decisions](../architecture-and-decisions.md).

## Decision

D1 is reachable from PHP Containers through official outbound handlers. However, no maintained, complete Yii2-specific D1 adapter was found in this bounded search. Existing PHP PDO wrappers provide porting material, not evidence that Yii2 ActiveRecord, Gii, migrations, and transactions work unchanged. For lowest course-delivery risk, use managed MySQL. If minimizing database operations is the overriding goal, allow one bounded D1 integration spike before selecting it, with explicit rejection of unsupported transaction semantics.

The complexity is not JSON-over-HTTP. It is preserving the database contract expected by Yii2 and course tooling. Do not replace real transactions with successful no-op methods.

## Existing internal context

Only `docs/internet-database-development-assignments.md` existed at investigation start. It requires a team site, front/admin functionality, more than ten tables, MVC contributions, database documentation, and an installation SQL export. The conversation supplies the additional PHP/Yii2 Advanced teaching context. No application code exists to validate.

## Official access mechanism

[Containers: Connect to Workers and Bindings](https://developers.cloudflare.com/containers/configuration/workers-connections/) explicitly includes D1 among bindings accessible through outbound handlers. A virtual HTTP hostname maps container requests to trusted Worker code with bindings. [Outbound traffic](https://developers.cloudflare.com/containers/configuration/outbound-traffic/) documents `outboundByHost` and direct interception APIs. This is official transport support, not a Yii2 database driver.

Desired topology:

```text
Yii2 in Linux PHP Container
  -> private virtual HTTP host / outbound handler
  -> D1 binding
```

A public, unrestricted SQL endpoint is unnecessary. Keep the bridge private to the outbound path; bind values separately from SQL; restrict payload sizes; return normalized results, changed rows, insert IDs, and structured errors. Do not blindly retry writes after an ambiguous response.

## Audited community candidates

Repositories were shallow-cloned into workspace `.cache/research/` and source inspected. No PHP or Composer executable was found on PATH, no test suites were executed, and no Cloudflare deployment was performed.

### L1: renoki-co/l1

- Repository: https://github.com/renoki-co/l1
- Inspected commit: `3f2b97816dd1210ec2e0ac1b01f9e19541db5370`.
- Files: `src/D1/Pdo/D1Pdo.php`, `D1PdoStatement.php`, `src/CloudflareD1Connector.php`, `composer.json`.
- Framework target: Laravel; REST D1 query API transport. Cloudflare lists it as a [community project](https://developers.cloudflare.com/d1/reference/community-projects/), not a Cloudflare-maintained adapter.
- `beginTransaction()` and `commit()` only change a PHP boolean. Each statement immediately sends a REST query. `rollBack()` is not overridden. This does not provide D1 transaction atomicity.
- `rowCount()` counts result rows rather than mutation changes; `fetch()` and `fetchColumn()` are not implemented by the wrapper. These are consequential Yii2 compatibility gaps.
- Constructor opens an unused in-memory SQLite PDO connection; Laravel/Illuminate dependencies are required. Not a drop-in Yii2 connector.

Pinned evidence: [PDO](https://github.com/renoki-co/l1/blob/3f2b97816dd1210ec2e0ac1b01f9e19541db5370/src/D1/Pdo/D1Pdo.php), [statement](https://github.com/renoki-co/l1/blob/3f2b97816dd1210ec2e0ac1b01f9e19541db5370/src/D1/Pdo/D1PdoStatement.php).

### ntanduy/cloudflare-d1-database

- Repository: https://github.com/TanDuy03/cloudflare-d1-database
- Inspected commit: `e8c950b8eeaa4fd9d547fa7f7dc27e8718591480`.
- Framework target: Laravel 10–13; PHP >= 8.2. REST and Worker bridge connectors exist.
- Source has more complete PDO fetching, changed-row metadata, error mapping, and a Worker transport. It includes mock-based Laravel integration tests and Worker tests. Their presence is not evidence of Yii2 or deployed D1 compatibility.
- PDO `beginTransaction()`, `commit()`, and `rollBack()` explicitly return successful no-ops; `inTransaction()` always returns false. Laravel connection offers exception/log/silent transaction modes, with silent as the default. That guard belongs to the Laravel connection and does not protect Yii2 automatically.
- Its PDO classes and connector are useful references for a separate Yii2 integration, not a finished Yii2 adapter. Payload binding order, repeated named parameters, fetch modes, cursor behavior, schema discovery, and error conversion need focused testing.

Pinned evidence: [PDO](https://github.com/TanDuy03/cloudflare-d1-database/blob/e8c950b8eeaa4fd9d547fa7f7dc27e8718591480/src/D1/Pdo/D1Pdo.php), [Laravel connection](https://github.com/TanDuy03/cloudflare-d1-database/blob/e8c950b8eeaa4fd9d547fa7f7dc27e8718591480/src/D1/D1Connection.php).

### pdo-cfd1 is a different runtime path

https://github.com/seanmorris/pdo-cfd1 implements a real PDO extension for PHP compiled with Emscripten/php-wasm and Worker D1 bindings. Its current README documents PHP 8.0–8.5 and custom builds. It is not a native Linux container PDO extension that can be installed unchanged for our architecture. Switching to PHP-in-Wasm is a separate runtime decision, outside this bounded deployment plan.

## Yii2 compatibility requirements

Yii2 upstream revision inspected: `608b5e253337e79e539a7dd47cc57275d9a6181d` (master at retrieval). Relevant files were downloaded into `.cache/research/yii2/`. This is upstream source analysis, not a dependency version already selected by this repository.

| Surface | Evidence and consequence |
| --- | --- |
| Connection | `yii/db/Connection.php` creates a PDO instance and initializes attributes; a custom factory/subclass must retain that lifecycle. Changing only a DSN cannot turn SQLite PDO into HTTP D1 access. |
| Command/results | `yii/db/Command.php` calls PDO statement binding/execution, fetching, `rowCount()`, and `closeCursor()`. Limited wrappers must match these behaviors, not only return arrays. |
| ActiveRecord | Needs correct schema, generated IDs, updates, deletes, validation queries, joins, relations, and exception mapping. Some basic CRUD may work after adaptation; not yet demonstrated. |
| Gii | Schema introspection must enumerate tables, columns, primary/foreign keys, and indexes. Yii2 SQLite Schema uses `sqlite_master`, `PRAGMA table_info`, `foreign_key_list`, `index_list`, and `index_info`. D1 documents corresponding schema PRAGMAs; feasible does not mean verified unchanged. Filter Cloudflare system tables. |
| Migrations | `Migration::up()/down()` wrap `safeUp()/safeDown()` in a Yii transaction. Replacing the driver without changing migration strategy can silently remove rollback guarantees. Wrangler SQL migrations or explicitly nontransactional Yii `up()/down()` are alternatives, with a documented source of truth. |
| Transactions | `Transaction::begin()` calls PDO `beginTransaction()`; commit/rollback depend on PDO transaction state. No-op PDO wrappers violate the contract. Nested transactions/savepoints need rejection or explicit redesign. |

Primary sources: [Connection](https://www.yiiframework.com/doc/api/2.0/yii-db-connection), [Transaction](https://www.yiiframework.com/doc/api/2.0/yii-db-transaction), [SQLite Schema](https://www.yiiframework.com/doc/api/2.0/yii-db-sqlite-schema), [Migration source](https://github.com/yiisoft/yii2/blob/608b5e253337e79e539a7dd47cc57275d9a6181d/framework/db/Migration.php).

## D1 semantics that matter

The current [D1 binding API](https://developers.cloudflare.com/d1/worker-api/d1-database/) exposes statements, atomic `batch()`, and Sessions. A batch rolls back as a group when a statement fails, but all statements are submitted up front. It cannot transparently implement arbitrary PHP code that reads, branches on the result, then writes within one open transaction. Sessions provide sequential consistency, not that transactional behavior. The documented API does not expose begin/commit for an interactive transaction.

For example, creating a project plus memberships can be one known SQL batch. But ordinary Yii code that saves a generated parent ID, reads application state, then branches to more writes cannot simply be buffered until commit without changing execution semantics. SQL constraints and single conditional updates often simplify this; arbitrary transparent emulation does not.

Current [D1 limits](https://developers.cloudflare.com/d1/platform/limits/) include 100 bound parameters per query, a 100 KB SQL statement limit, 30-second maximum query duration, and 50/1000 queries per Worker invocation on Free/Paid. Limits apply to individual statements inside batches. These are not course-site capacity blockers, but affect bulk insertion and migration tooling. Do not infer a universal 100-statement batch limit from a community package constant.

[SQL statements](https://developers.cloudflare.com/d1/sql-api/sql-statements/) documents supported schema inspection PRAGMAs. [Foreign keys](https://developers.cloudflare.com/d1/sql-api/foreign-keys/) documents enforcement and deferred checking. [Import/export](https://developers.cloudflare.com/d1/best-practices/import-export-data/) requires removing transaction markers from SQLite SQL dumps; exports are SQLite/D1 SQL, not a MySQL dump.

## First integration gate, if D1 remains attractive

Time-box a small real workflow, not a generic PDO rewrite:

1. Two related tables with unique and foreign-key constraints; a Yii2 model, controller, view, and local Gii generation.
2. Signup/login and create/edit a project with two memberships; verify insert ID, row counts, binding order and repeated parameters, relation loading, pagination count, and integrity errors.
3. Force the second write to fail. Demonstrate full rollback through explicit D1 batch, or reject unsupported Yii transaction calls immediately. Never pass this gate with no-op transactions.
4. Deploy through the outbound helper; stop/restart the PHP container and verify data, login state, and uploads survive. Restore schema/data from the documented installation/export procedure.

Pass means this constrained application can use an explicitly limited D1 integration. It does not prove a general Yii2 PDO driver. Fail or exceed the time box: choose managed MySQL and retain native Yii tooling rather than accumulating driver exceptions.

## Search boundary

Queries included `Yii2 Cloudflare D1 PDO adapter PHP`, `"yii2" "D1" Cloudflare adapter`, `PHP Cloudflare D1 driver PDO`, and official Cloudflare community listings. No Yii2-specific supported adapter surfaced. This is an evidence-based bounded search result, not proof that none exists anywhere. Source access and local probes were sufficient to identify the decision blocker; production readiness and latency remain unmeasured.
