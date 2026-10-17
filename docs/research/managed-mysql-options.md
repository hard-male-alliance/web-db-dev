# Managed MySQL options for Yii2 on Cloudflare Containers

Research date: 2026-10-17. Scope: a small mainland-China university team website, PHP + Yii2 Advanced, Cloudflare Containers for application compute, GitHub Actions deployment. No accounts, paid resources, implementation changes, or live database tests were performed.

**Historical project decision (now superseded by local Docker Compose):** the user selected Google Cloud SQL for MySQL. The recommendations below preserve the earlier candidate comparison, not the current hosting selection. No cloud database is needed for the current local-demo baseline. See [Architecture and decisions](../architecture-and-decisions.md).

## Decision

**First choice: Aiven Free MySQL if strict MySQL/InnoDB compatibility matters. Alternative: TiDB Cloud Starter if serverless billing matters more than exact MySQL behavior.** Neither requires operating a database inside an ephemeral application container. Database scale-to-zero is unnecessary when a suitable managed database already costs $0.

Existing internal material: `docs/internet-database-development-assignments.md` requires a team website and more than ten tables; it does not itself explicitly require MySQL. Language/framework constraints reported in the conversation remain relevant; do not reinterpret SQL compatibility as permission to substitute database products.

## Shortlist

| Option | Engine and Yii2 path | Verified starting cost | Idle behavior | Main trade-off |
| --- | --- | --- | --- | --- |
| Aiven Free / Developer MySQL | Actual managed MySQL; ordinary Yii2 `yii\\db\\Connection` and `pdo_mysql` | Free $0: 1 GB disk, 1 GB RAM, 1 CPU; Developer $5/month: 8 GB disk | Not request-driven scale-to-zero; free service can be powered off after inactivity and manually restarted | Least framework adaptation; free/developer plans cannot choose a specific cloud/region |
| TiDB Cloud Starter, MySQL-compatible mode | Distributed TiDB, not MySQL/InnoDB; MySQL TCP protocol and existing MySQL clients | $0 inside each eligible instance's 5 GiB row + 5 GiB column storage and 50 million RU/month allowance; no card needed | Vendor advertises on-demand compute scaling to zero; persistent managed storage remains | Normal PDO/MySQL integration is plausible, but schema/tooling compatibility must be tested |
| AWS Aurora MySQL Serverless v2 | MySQL-compatible Aurora, not a vanilla MySQL installation | No universal $0 total floor: paused compute $0, storage/I/O/network still billed; official example uses $0.12/ACU-hour, region-specific | Supported versions can auto-pause at 0 ACUs; typical resume about 15 seconds | Mature managed persistence but AWS network, security, billing setup is excessive for this course |

Prices are USD, before possible taxes. Cloudflare application costs are additional. Aurora's published example is not a Singapore quote. No paid total is estimated without region/workload inputs.

## Evidence and caveats

### Aiven

- [Pricing](https://aiven.io/pricing/mysql) supplies the $0/$5 plans and region-selection restrictions.
- [Free-tier reference](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier) states no card/time limit, backups, one free service per type, 76 connection limit, no SLA, and inactivity power-off policy. **This is not automatic wake-on-query.** Check and wake the database before a demo, and retain independent SQL exports. Bound container count and PHP-FPM workers to avoid exhausting the small connection budget.
- [Backup overview](https://aiven.io/docs/products/mysql/concepts/mysql-backups) describes daily full backups and continuous binary logs; retention and recovery capabilities depend on plan. Do not infer premium restore guarantees for the free plan.
- [Official PHP connection example](https://aiven.io/docs/products/mysql/howto/connect-with-php) confirms PDO connectivity, but its `sslmode`/`sslrootcert` DSN example should **not** be copied as proof of secure PDO MySQL configuration. Use the actual PDO MySQL SSL attributes and verify the negotiated TLS connection; see [PHP driver](https://www.php.net/manual/en/ref.pdo-mysql.php) and [PDO MySQL constants](https://www.php.net/manual/en/class.pdo-mysql.php).

### TiDB

- [Detailed pricing](https://www.pingcap.com/tidb-cloud-starter-pricing-details/) confirms the per-instance allowance and explains why marketing also advertises 25 GiB/250 million RU: that is the sum across five eligible instances, **not one instance's allowance**. Paid rates vary by region; do not copy the cheapest displayed rate into a Singapore estimate.
- [Plan selection](https://docs.pingcap.com/tidbcloud/select-cluster-tier/) explains no-card free usage, spending limits, and that exhausted free quota rejects new connections and throttles existing ones until limits are raised or quota resets. RU means resource consumption, not one SQL query or one HTTP request.
- [Product description](https://www.pingcap.com/tidb/cloud/) advertises compute scaling to zero. [Connection documentation](https://docs.pingcap.com/tidbcloud/connect-to-tidb-cluster-serverless/) describes MySQL TCP public endpoints. [TLS documentation](https://docs.pingcap.com/tidbcloud/secure-connections-to-serverless-clusters/) requires TLS for public connections and explains CA roots.
- [MySQL compatibility](https://docs.pingcap.com/tidbcloud/mysql-compatibility/) documents unsupported stored procedures/functions, triggers, and events. Reporting an InnoDB-compatible metadata value does not mean TiDB runs InnoDB. [Foreign keys](https://docs.pingcap.com/tidbcloud/foreign-key/) are supported and GA from v8.5; outdated claims that TiDB always ignores foreign keys are wrong. Test schema introspection/Gii, migrations, identity generation, transactions, collation, and SQL export/import before choosing it.
- [Starter launch/rename](https://www.pingcap.com/blog/introducing-tidb-cloud-starter-auto-scaling-plan-renamed/) lists AWS Tokyo and Singapore, among other regions. Confirm the live console's currently available regions when provisioning.

### Aurora

[Auto-pause](https://docs.aws.amazon.com/AmazonRDS/latest/AuroraUserGuide/aurora-serverless-v2-auto-pause.html) and [pricing](https://aws.amazon.com/rds/aurora/pricing/) establish that reliable MySQL-compatible zero-compute idle operation exists. It is not equivalent to zero total cost. Open user connections can prevent pause, and application connection timeouts must accommodate resume. Prefer Aiven/TiDB for this small project unless AWS itself is a learning objective.

## Container connection and user journey

[Cloudflare outbound rules](https://developers.cloudflare.com/containers/configuration/outbound-traffic/) distinguish HTTP helpers from non-HTTP traffic. Native MySQL TCP does not travel through the HTTP outbound helper; the container needs Internet egress enabled and a reachable database endpoint. Do not assume a stable source IP for database allowlisting; verify network-policy feasibility before signup/commitment. Keep least-privilege credentials in secrets, enable certificate verification, and never disable TLS verification as a connectivity fix.

Recommended acceptance probe, once a provider is selected:

1. Use a genuine PHP/Yii2 container with `pdo_mysql` and CA roots to run migrations and Gii schema inspection.
2. Complete login -> create project -> add member -> post message -> moderate message, including non-ASCII names and foreign-key rejection.
3. Let application compute sleep, then wake it and verify saved data and login-session continuity. Sessions and uploads must not live solely on container disk.
4. Export `data/install.sql`, recreate an empty database, import it, and repeat the critical journey.
5. Run the deployed URL from the actual mainland campus/mobile network before the demo. Neither brand nationality nor an Asia region proves acceptable cross-border latency/reachability. Aiven Free's assigned region can be unsuitable; TiDB Singapore/Tokyo is a candidate, not a latency guarantee.

## Versus D1 adaptation

A hosted MySQL endpoint preserves Yii2's normal PDO connection, Active Record, schema discovery, migrations, transactions, and Gii workflow with minimal app-level changes. A D1 HTTP helper only supplies transport; full Yii2 compatibility needs additional adapter coverage, especially schema introspection and transaction semantics. Unless the parallel D1 investigation finds a mature tested adapter, **choose managed MySQL rather than writing a database driver for a team-homepage assignment**. TiDB is a middle path: preserves MySQL wire protocol while trading some engine fidelity for managed serverless operation.
