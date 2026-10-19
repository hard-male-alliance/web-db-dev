# Independent application review

Reviewed: 2026-10-19 (Asia/Singapore). Scope: the emerging course application,
shared models/configuration, installer/schema, demo router and read-only Docker
integration. This is a change-level review, not a penetration test or a claim of
public-production readiness. No production files were modified by the reviewer.

## Findings and disposition

| Priority | Finding | Evidence and impact | Disposition |
| --- | --- | --- | --- |
| P1 | ActiveForm default asset publishing conflicts with read-only containers | `yii\\widgets\\ActiveForm::run()` calls `registerClientScript()`, which publishes vendor assets into `@webroot/assets`. The image runs non-root with read-only `/app`; login, guestbook and editors could fail during rendering. Inline initialization also conflicts with the router CSP. | Fixed and source-verified across all seven form views: both client script generation and client validation are disabled, retaining server-side validation and CSRF. |
| P2 | Accepted VARCHAR lengths exceeded database widths | Original `RecordForm` accepted 255-character member names against `VARCHAR(100)`, 255-character project/update titles against `VARCHAR(200)`, and similar location/email mismatches. Strict MySQL rejects a form the application calls valid. | Fixed: explicit per-field/per-kind limits now match SQL. |
| P2 | Project edits erased membership roles | `actionProject()` deleted all memberships and reinserted them with role `贡献者`, including unchanged members. A title-only edit lost seeded or retained architecture/design role data. | Fixed: source now computes added/removed IDs and leaves retained rows intact. Require regression coverage through actual project-save HTTP. |
| P2 | Member update editor listed unrelated private draft projects | `actionUpdate()` originally sent every project to its dropdown while writes checked participation. Authenticated members could read otherwise unassigned draft names. | Fixed: member dropdown query is restricted by `project_members`; admin retains full visibility. |
| P2 | TEXT validation counted characters, not storage bytes | 17,000 U+1F31F characters produce 68,000 UTF-8 bytes. `RecordForm` accepted the body (20,000-character limit), but MySQL TEXT rejected it with error 1406 / SQLSTATE 22001. Same mismatch affects bio/description. | Fixed and independently reproduced: byte-aware `strlen <= 65535` validation now rejects the probe while preserving the existing 20,000-character limit for fitting encodings. |

### Reproduced storage mismatch

The isolated `.temp/review-contract.php` probe uses the configured native PHP and
the disposable MySQL 8.4.11 database at `127.0.0.1:13367/application_test`. It
validates an update model, then attempts an update inside a transaction that is
unconditionally rolled back. Initial output:

```text
Form accepts 68,000-byte utf8mb4 body: yes
SQL rejected body: SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'body' at row 1
```

After the fix, the same probe returns a model validation error on `body` before
the application can execute SQL. Its intentionally direct SQL comparison still
reports error 1406; this confirms the storage boundary, not a remaining failure.
All five identified findings above are resolved in the reviewed source. No
additional substantive issue was identified within the stated review scope.

## Examined boundaries

- Public project details reject unpublished records. Public update queries require
  both update publication and, when linked, project publication.
- Public guestbook selects no email column, accepts only form-owned fields, and
  inserts pending status explicitly. Replies are only displayed under approved
  public messages.
- Backend authentication uses Yii AccessControl. Admin-only team/member/moderation
  paths explicitly enforce administrator role; project participation and update
  author identity restrict member write paths. Profile IDs come from identity,
  not request attributes.
- Yii CSRF validation remains enabled. Moderation/logout use POST-only filters.
  Shared frontend/backend session identity is intentional single-sign-on for one
  origin; database identities are reloaded on requests, including enabled status.
- Download paths are database-resolved IDs, canonicalized with realpath, checked
  for containment under `data/team`, and sent as attachments. The demo router
  serves only explicit local asset extensions and otherwise dispatches application
  requests, not repository files.
- Writes and audit insertion share transactions. InnoDB foreign keys and checks
  represent domain constraints. Installer uses a MySQL advisory lock and seeds
  only a completely empty domain, rather than replacing retained rows/passwords.
- Docker runtime writes are explicitly relocated to `/tmp`; native runtime writes
  stay under project `.temp`. Session loss on container replacement is acceptable
  logout, not business-data loss. Application source and submission PDFs stay
  immutable in the image; persistent business data is in MySQL.

These are static control-flow observations unless a concrete probe is described.
The validation agent owns full HTTP journeys, backup/restore and test results.
Read-only Docker execution still requires the dedicated container validation
record; native Windows execution alone cannot establish its success.

## Assignment boundary

The schema contains 14 domain/support tables, not the infrastructure probe alone.
Six editable course documents and generated download PDFs are being delivered by
the documentation owner. Demo member names/IDs are explicit placeholders, not
evidence that real students authored the required MVC files. Real team identity,
historical workload attribution, the recorded walkthrough, TA demonstration and
email submission cannot be fabricated by implementation or static review.

## External grounding

- [Yii security best practices](https://www.yiiframework.com/doc/guide/2.0/en/security-best-practices): retain CSRF defenses and encode untrusted output; cookies alone are not the write authorization boundary.
- [Yii ActiveForm API](https://www.yiiframework.com/doc/api/2.0/yii-widgets-activeform): client script generation is a configurable form concern, separate from server-side validation.
- [MySQL CHAR/VARCHAR semantics](https://dev.mysql.com/doc/refman/8.0/en/char.html): strict SQL rejects oversized values rather than accepting application-level character assumptions.
- The architecture document records the relevant USENIX Security 2023 framework-CSRF research and its limited practical implication here: test actual negative HTTP flows rather than assuming framework use proves safety.
