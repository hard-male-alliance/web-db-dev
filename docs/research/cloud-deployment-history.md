# Historical cloud deployment discussion (superseded)

This snapshot preserves the earlier discussion, including its later local-demo amendment. It is not the current setup guide. See [current decisions](../architecture-and-decisions.md) and the [README](../../README.md).

# Project scope, architecture, and technology decisions

Date: 2026-10-17 (Asia/Singapore).
Status: local-demo baseline; pinned PHP/Composer/Yii2 dependencies installed and smoke-tested, but the application and database are not yet scaffolded.

## Current baseline: local demo supersedes cloud deployment

The user subsequently chose a local course demonstration rather than cloud hosting. Use local PHP + Yii2 + MySQL. Cloudflare, Rust routing, Cloud SQL, and cloud deployment below are preserved as historical design exploration, not current prerequisites or provisioned infrastructure. No paid resources were created.

The first implementation step installs project-local Windows x64 PHP 8.4.26 and Composer 2.10.3, with SHA-256-verified downloads, and locks Yii2 2.0.55 plus transitive dependencies in `composer.lock`. See the [setup and validation instructions](../../README.md). MySQL version/install and Yii2 Advanced scaffolding remain pending. A future Compose environment is optional; Docker is not currently installed. Local MySQL must use durable storage and a tested SQL export/restore path.

## 1. Executive decision

Build a team website with PHP and Yii2, following the course's MVC teaching approach. Run the application and MySQL/InnoDB locally for demonstration and documented restoration. The previous Google Cloud SQL / Cloudflare deployment proposal is deferred. GitHub remains the source-management platform; CI may be added separately without requiring cloud deployment.

The goal is a coherent course deliverable, not a database driver or a distributed database project. Keep application business logic in Yii2; infrastructure code should remain small.

## 2. Decision status

| Area | Current decision | Status |
| --- | --- | --- |
| Product | Team website with public presentation and an administration area | Course-aligned baseline; detailed feature scope pending |
| Application | PHP 8.4.26 + Yii2 2.0.55 | Installed dependencies and smoke-tested; application scaffolding pending |
| Structure | Yii2 Advanced template, public and administration applications | Preferred baseline matching the slides; not yet scaffolded |
| Rendering | PHP view templates producing server-rendered HTML; JavaScript for selected interactions | Preferred baseline; no independent SPA planned |
| Database | MySQL using InnoDB, PHP `pdo_mysql` | Selected; server version pending |
| Database hosting | Local MySQL | Current direction; server not installed or version-pinned yet |
| Application hosting | Local Windows PHP | Runtime installed; Compose is optional future work |
| Edge/control code | None required locally | Rust/DO proposal deferred |
| Delivery | Local setup scripts and locked dependencies | GitHub Actions is optional future CI, not a required deploy path |
| Uploads | Local durable project storage | Path/permissions and backup pending; R2 deferred |
| Session persistence | MySQL-backed Yii sessions | Recommendation, not yet selected |
| Cloud SQL connection | Not required locally | Historical candidate, deferred |

Do not interpret recommendations as already provisioned services or completed implementation.

## 3. Course constraints and source boundaries

Internal assignment summary: [Course assignment summary](../internet-database-development-assignments.md).
Additional source reviewed: the 28-page lecture PDF `互联网数据库开发L5-L8_2026.pdf`, supplied by the user. The PDF is not currently stored in this repository; the page references below are provenance for the discussion, not a substitute for preserving the original course material.

### Requirements represented in the existing summary

- Give the team a name and build a team website with public and administrative functionality.
- Public functionality includes team/member presentation and messages; administrative functionality includes information editing and publishing.
- Use more than ten database tables: at least eleven.
- Select version-control and project-management tools.
- Use consistent naming and PHP file comment headers.
- Each member contributes at least one file in each MVC layer.
- Export database SQL to `/data/install.sql`; place team submissions under `/data/team/`; expose submission download links on the site.
- Deliver requirements, design, implementation, user manual, deployment documentation, and a presentation. Explain responsibilities and workload in the presentation.
- Dynamic charts and one-click deployment are bonus items, not mandatory architecture requirements.

### Clarifications from the lecture PDF

| PDF pages | Observation | Interpretation |
| --- | --- | --- |
| 6, 21 | MyISAM/InnoDB comparison and a MySQL connection example | MySQL is the teaching baseline; examples alone do not prove an exclusive database restriction |
| 10 | Yii2 initialization and separate `frontend` / `backend` web entry points | Teaching follows the Advanced-template structure |
| 13-15, 19-22 | MVC captcha example and Gii model/CRUD generation | Preserve meaningful Yii2 model, view, controller, and database-tool workflows |
| 23 | PHP headers, SQL export, submission paths, per-member MVC contributions | Explicit coding/submission instructions |
| 24-27 | Absolute template, layout switching, `AppAsset.php`, static assets, and sample archive | Template-based rendering is taught; mandatory use of the particular template is not established |
| 28 | Team assignment 7: recorded walkthrough | Additional deliverable missing from the earlier summary; include in delivery planning |
| 28 | Submission date placeholder is blank | Does not establish a deadline; the earlier summary's November 30 also has no confirmed year |

The user reports restrictions on language and framework. PHP/Yii2 are selected accordingly. This PDF does not itself establish that every alternative is prohibited, and no specific deployment platform restriction was identified.

The user previously requested removal of all personal-assignment sections from the local summary. That edit changes the project document's scope; it does not establish that the instructor cancelled those assignments. This decision document covers the team project only.

## 4. Proposed product scope

The proposed theme is a **team project archive**: a team homepage that explains who the members are, what they built, and how they collaborated. The user accepted proceeding with a course-aligned team website; the archive feature set remains a proposal, not a frozen specification.

Core public journey:

1. Discover the team and members.
2. Browse a project, its contributors, and progress.
3. Read published updates and leave a message.
4. Download project/course documents.

Core administration journey:

1. Sign in as a member or administrator.
2. Edit a profile and project information.
3. Publish an update and manage documents.
4. Moderate and reply to messages.

Candidate extensions: project milestones, development notes, tags, and progress charts. Do not add live chat, AI features, or a full project-management platform to the initial scope.

A possible 13-table model consists of users, team settings, member profiles, skills, member-skill associations, projects, project memberships, milestones, posts, tags, post-tag associations, messages, and project documents. This is an illustration, not final schema approval. Tables must express real entities or relationships rather than artificially split fields to meet a count. Infrastructure tables should not be the only reason the required domain-table count is reached.

## 5. Application and rendering model

PHP executes on the server, not in the browser. Yii2 controllers obtain model data and render PHP view templates into HTML. CSS handles presentation; browser JavaScript adds interactions such as asynchronous messages or charts.

In Yii2 Advanced, `frontend` means the public-facing application and `backend` means the administration application. Both contain server-side PHP. This naming is not the same as the browser/server boundary in a separate frontend/API architecture.

Keep database operations in the appropriate model/service layer, not inside view templates. Use Yii's HTML escaping, form validation, authentication, authorization, and CSRF protection. A visually modern site does not require replacing Yii views with an independent React/Vue application.

## 6. Historical cloud deployment architecture (deferred)

```text
Browser
   |
   v
Rust Worker: small routing/proxy layer
   |
   v
Rust Durable Object: stable app identity, startup/readiness, lifecycle
   |
   v
Cloudflare Container
   +-- PHP + Yii2 public/admin applications
   +-- HTTP server / PHP execution configuration: to be selected
   +-- Optional Cloud SQL Auth Proxy, loopback listener only
          |
          v
Google Cloud SQL for MySQL + InnoDB

Application uploads ---> R2 or another durable store (proposed)
GitHub Actions ------> build/check/deploy Worker and container image
```

Start with a single logical application instance and bounded capacity. Do not create one full Yii2/MySQL installation per user. Public/admin hostnames or path routing and the container's internal web-server setup remain implementation decisions.

### Durability and scale-to-zero

- PHP application compute may stop when idle. Cold starts must wait for HTTP readiness before forwarding traffic.
- Container disk is ephemeral by default. Durable Object storage does not automatically make container files durable.
- Store business records in Cloud SQL, not in a MySQL process on ephemeral container disk.
- Store sessions and uploaded files outside ephemeral disk. Keep cookie-validation and other application secrets stable across instances and deployments.
- Cloud SQL is not assumed to pause and wake on individual queries. Manual stop/start is separate; storage and IP-related charges can remain while stopped.
- Container snapshots are point-in-time filesystem state, not a general guarantee of durable database commits. They are also tied to the image version in current documentation. No snapshot-dependent database design is selected.

### Rust Containers API status

At investigation time, the published `worker` 0.8.7 API documents `State::container() -> Option<Container>`, with `running`, `start`, `get_tcp_port`, `signal`, `destroy`, and `wait_for_exit` on `Container`. This supports a Rust-based control path in principle, not merely a Rust entrypoint forwarding to a TypeScript DO.

That documented wrapper does not list every newer runtime capability, including the current inactivity-timeout and snapshot APIs. Pin the chosen crate/runtime versions and validate the actual lifecycle requirements. Small `wasm-bindgen` interoperability bindings may be necessary; they have not been implemented or tested. Do not assume complete feature parity, and do not add a TypeScript control layer unless a demonstrated gap warrants it.

## 7. Historical Cloud SQL provisioning proposal (deferred)

The user selected Google Cloud rather than continuing to optimize free database hosting. Provisioning has not been authorized as an execution task or performed by this documentation work.

Initial recommendation: evaluate Cloud SQL Enterprise, a single-zone small instance, and a modest bounded storage configuration. Confirm region, MySQL version, backups, recovery capabilities, and estimated total cost before creating resources. Do not accept a large/default high-availability configuration without a reason.

Total cost includes instance uptime, storage, backup usage, and applicable public-network data transfer to Cloudflare, plus separate Cloudflare application charges. No exact monthly estimate is established. Do not use an unqualified global price for a region-specific service.

For secure connectivity, evaluate Cloud SQL Auth Proxy inside the PHP container, listening only on loopback. It avoids an IP allowlist dependency for the public-IP connection path, but still requires an actual reachable endpoint, outbound networking, Google Cloud credentials/permissions, and database authentication. A private-IP-only instance is not reachable from Cloudflare merely by adding the proxy.

Prefer short-lived credentials where practical; if keys are needed, store them as deployment secrets with least privilege, never in the image or repository. Keep application and migration database privileges separate where useful. Do not open a database to all source addresses as a routine fix for dynamic container egress.

Limit PHP worker count and container instance count so the sum of database connections stays within the selected instance's capacity. Avoid blindly retrying writes after a lost response: the database may already have committed them.

Set billing monitoring before deployment. An alerts-only budget is notification, not a hard spending cap. Confirm any separately available enforced-budget capability and its consequences before relying on it.

## 8. Alternatives considered

| Alternative | Why it is not the selected baseline |
| --- | --- |
| GitHub Pages as the full application host | Serves static assets; cannot run the PHP backend or database. GitHub remains the source/deployment platform |
| TypeScript-only Workers backend | Attractive absent course restrictions, but not the chosen PHP/Yii2 course route |
| PostgreSQL | Technically viable with Yii2; user chose MySQL for closer alignment with teaching material |
| SQLite on container disk | Simplifies a normal persistent-volume deployment, but Cloudflare's default ephemeral disk does not supply that prerequisite |
| Durable Object SQLite | Durable and useful, but not a local SQLite file Yii2/PDO can directly open |
| D1 through an outbound helper | Official access mechanism exists, but full Yii2/PDO compatibility and interactive transactions remain the integration cost |
| Self-hosted MySQL inside Cloudflare Containers | Couples database recovery and storage correctness to container lifecycle; unnecessary for this deliverable |
| Aiven managed MySQL | Viable low-cost option; superseded by the user's Google Cloud choice, not rejected as technically unsuitable |
| TiDB Cloud Starter | Managed MySQL-protocol alternative, but not MySQL/InnoDB and requires compatibility validation |

See [D1 investigation](yii2-d1-feasibility.md) and [managed MySQL investigation](managed-mysql-options.md) for evidence, audited packages, and candidate trade-offs. The research recommendation for Aiven was made before the final Google Cloud selection.

## 9. Delivery and acceptance gates

For the current local baseline, replace the cloud application container/Cloud SQL in the following planned application checks with local PHP/MySQL, and test restart rather than cloud cold start. Cloud-specific CI/deployment steps are deferred. The implemented toolchain HTTP smoke check does not constitute completion of these application-level gates.

### First vertical slice

Before building the full site, deploy one minimal Yii2 workflow using the real application container and selected Cloud SQL connection:

1. Apply migrations and inspect related tables with Gii.
2. Sign in, create a project, attach a member, and display the result publicly.
3. Force a related write to fail and verify actual transaction rollback and foreign-key enforcement.
4. Let the PHP container stop, then repeat access after cold start; confirm data and session continuity.
5. Confirm Chinese names/content round-trip correctly.

### Complete user journey

Verify login -> edit member profile -> publish project/update -> visitor message -> moderation/reply -> document download. Run the deployed URL on actual mainland campus/mobile networks; provider brand and an Asian database region do not prove acceptable reachability or latency.

### Deployment and restore

- GitHub Actions should run relevant PHP/Rust checks and a database-backed workflow, build the image, serialize production deploys, apply controlled migrations, deploy, and check the real URL.
- Run migrations once per release, not on every container wake. Keep changes compatible with the preceding application version during rollout; do not assume an application rollback reverses database changes.
- Keep production credentials out of untrusted pull-request jobs.
- Export `/data/install.sql`, restore into an empty database, and repeat the main journey. Backups are not proven until restoration works.
- Include deployment instructions, presentation workload attribution, and the recorded walkthrough found on PDF page 28.

## 10. Open decisions

1. Team identity, exact features, member responsibilities, and final schema.
2. PHP/Yii2/MySQL versions and dependency support/security compatibility.
3. Local MySQL version, install method, data directory, backups, and credentials.
4. Whether to adopt Docker Compose for reproducible multi-service startup.
5. Whether cloud hosting is needed later; GCP, Rust/DO, and scale-to-zero are deferred.
6. HTTP server, public/admin routing, custom domain, uploads, and sessions.
7. Instructor confirmation of any restrictions beyond the supplied material, and actual deadline.

No accounts were created, paid resources provisioned, live database connections tested, migrations run, or application deployments performed during the discussion and research.

## References

- [Yii2 Advanced application structure](https://www.yiiframework.com/extension/yiisoft/yii2-app-advanced/doc/guide/2.0/en/structure-applications)
- [Yii2 views](https://www.yiiframework.com/doc/guide/2.0/en/structure-views)
- [Yii2 database connection API](https://www.yiiframework.com/doc/api/2.0/yii-db-connection)
- [Cloudflare Containers API](https://developers.cloudflare.com/containers/api/)
- [Containers durability FAQ](https://developers.cloudflare.com/containers/faq/)
- [Container snapshots](https://developers.cloudflare.com/containers/guides/snapshots/)
- [Container access to bindings](https://developers.cloudflare.com/containers/configuration/workers-connections/)
- [Rust State API](https://docs.rs/worker/0.8.7/worker/durable/struct.State.html)
- [Rust Container API](https://docs.rs/worker/0.8.7/worker/struct.Container.html)
- [GitHub Actions deployment](https://developers.cloudflare.com/workers/ci-cd/external-cicd/github-actions/)
- [Cloud SQL pricing](https://cloud.google.com/sql/pricing)
- [Cloud SQL stop/start behavior](https://docs.cloud.google.com/sql/docs/mysql/start-stop-restart-instance)
- [Cloud SQL Auth Proxy](https://docs.cloud.google.com/sql/docs/mysql/connect-auth-proxy)
- [Google Cloud alerts-only budgets](https://docs.cloud.google.com/billing/docs/how-to/budgets)
