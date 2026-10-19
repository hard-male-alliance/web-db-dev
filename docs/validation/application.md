# Application acceptance validation

Date: 2026-10-19 (Asia/Singapore). Scope: the working tree application built for
the course requirements in `docs/internet-database-development-assignments.md`.
This is independent executable evidence, not a claim about future revisions.

## Environment and isolation

- Windows x64; Python 3.14; project-local PHP 8.4.26 / Yii 2.0.55.
- Actual MySQL Community 8.4.11 / InnoDB, not SQLite or a mocked DB.
- Read-only reuse of `D:/MySQL/mysql-8.4.11-winx64/bin` binaries; independent
  mysqld data/logs under project `.temp/application-mysql`, loopback port 13367.
  The host's existing `D:/MySQL/data` was not modified.
- Seeded disposable database `application_test`. HTTP application uses the
  dedicated `application` account, scoped to this DB, not root.
- PHP built-in server `http://127.0.0.1:18080`, document root `frontend/web`,
  router `frontend/web/router.php`, runtime `.temp/application-runtime`.
  Built-in server is a local demo/test server, not a production deployment.

## Executed HTTP user journeys

Command:

```powershell
python tests/application_journey.py --base-url http://127.0.0.1:18080
```

Final expanded run: **6 scenarios passed, 8.479 seconds**. Each scenario includes
multiple independent assertions/boundary cases. Python standard-library HTTP
clients maintain real cookie sessions, parse actual CSRF fields, submit real
forms, follow redirects, and use a fresh visitor session for public assertions.
The same command accepts a Docker/CI endpoint; it does not depend on local PHP.
Use a disposable, seeded DB: this suite intentionally writes unique marker rows.

| Intended contract | Observed evidence |
| --- | --- |
| Public team/member/project/update/message/submission pages | All return 200 through PHP + Yii + MySQL; health JSON returns 200 |
| Private implementation must not be served | Attempts for `.env`, SQL and PHP source do not disclose their bytes |
| Login, role scope and logout | Bad password stays on login; member logs in; admin-only team/member/message management and another author's update return 403; logout GET 405, missing CSRF 400, valid POST ends authenticated access |
| CSRF on visitor/admin writes | Missing token rejects message, project publication and logout with 400; rejected publication stays private |
| Own profile edit; no privilege escalation | Member saves Chinese/emoji profile and sees it in a fresh public session; injected id/role does not grant admin |
| Project draft -> publication -> update | Draft absent publicly; progress -1/101/fraction/text rejected; 201-character Chinese title rejected; 0 and 100 accepted; published project and update become visible |
| Membership authorization | After admin removes member from a new project, their edit GET is 403 and attaching an update is rejected without public mutation |
| Visitor -> moderation -> reply | Injected approved status cannot bypass pending review; new message absent publicly; admin approves/replies; fresh visitor sees escaped content and reply, never the private email |
| Message size boundaries | Empty/81-character names and empty/2/2001-character bodies rejected; names of length 1/80 and emoji bodies of length 3/2000 accepted |
| Six deliverable downloads | IDs 1..6 return actual `%PDF-` bytes and attachment headers; invalid/negative/traversal/SQL-like IDs return 400/404 |

The role contract is collaborative: members may manage projects they participate
in, create a project with their own membership, and manage their own updates.
They are not blanket-denied every administration screen. This was explicitly
confirmed with the application owner; tests avoid encoding the earlier tentative
admin-only interpretation.

## Defects detected and repaired

The first representative run exposed two real integration failures rather than
merely counting passing unit tests:

1. Every `ActiveForm` page failed with 500 because Yii attempted to publish
   `vendor/bower/jquery/dist` assets, while dependencies are in `bower-asset`.
   The final server-rendered forms disable unnecessary client script/validation;
   no writable public asset directory is required for these forms.
2. Successful login and profile pages failed with 500 because the plain `User`
   class had getters but did not implement Yii property access. Extending
   `yii/base/BaseObject` restored the declared `username`/`memberId` semantics.

The identical user workflow rejected the broken revisions and passed after the
owner's fixes. One later logout failure was an isolated harness mistake: it tried
to GET the POST-only logout endpoint to obtain CSRF. The harness now reads the
token from the actual dashboard form; the 405 contract was retained.

## Database installation and restoration

The schema owner independently exercised `scripts/install.php` twice against
this isolated MySQL instance: changed password/tagline survived, deleting a demo
milestone did not cause resurrection, invalid FK/duplicate username/progress 101/
skill level 6 were rejected, and legal progress 0/1/50/99/100 accepted. Their
reproduction is `.temp/check-installer.php`; these claims were reported by the
schema owner, whereas the HTTP and restore runs above/below were executed by
the application validator. The final schema contains **14 domain tables**.

After HTTP edits, executed:

```powershell
$env:DB_HOST='127.0.0.1'
$env:DB_PORT='13367'
$env:DB_DATABASE='application_test'
$env:DB_USERNAME='root'
$env:DB_PASSWORD=''
python tests/application_restore.py --mysql-bin D:/MySQL/mysql-8.4.11-winx64/bin --keep-restored
```

The root account is used only for this disposable server's create/drop-new-DB
test. Normal HTTP uses its restricted application account. The script writes
`.temp/application-restore/snapshot.sql`, imports a newly named database, and
compares all rows in every table (order-independent). It never resets the source
DB. Without `--keep-restored`, it removes only its own generated restore DB.

Result: all 14 restored tables equal the source, including profile edits,
published content, approved messages, replies, credentials, memberships and
audit rows. Recorded row counts: 27 audit logs, 3 members, 6 replies, 14 messages,
9 memberships, 7 projects, 8 updates, 6 submissions, 2 users; remaining reference
tables also matched. Dump SHA-256:
`3fbbfd7e7cd84d3f190f3fa2b71362705d813e8896742e0b360988c3d0e000de`.
Retained isolated target: `application_restore_27fc29cf7b84`.

## Limits

- This validates native Windows PHP/MySQL plus real HTTP, not the current Docker
  image, read-only container deployment, another team member's host, or macOS.
- Database restoration was verified by complete row comparison. A separate HTTP
  server pointed at the restored DB could not be started because execution policy
  rejected that command; restored-DB browser sessions are therefore not claimed.
- Browser layout, responsive behavior, accessibility, charts, screenshots and
  recorded walkthrough are owned by the parent's separate browser QA.
- No load/concurrency benchmark, SMTP/email submission, external public hosting,
  deadline confirmation, or real student contribution history is established here.
- HTTP boundary sweeps are deterministic property-style checks, not exhaustive
  random generation. The suite does not prove absence of every security flaw.

Local raw outputs are in `.temp/application-runtime/journey-result.txt` and
`restore-result.txt`. Durable reproductions are `tests/application_journey.py`
and `tests/application_restore.py`.


## Parent integration and browser acceptance

The parent reran all six HTTP scenarios after the final privacy/navigation fixes:
**6 passed in 8.346 seconds**. Reinstallation of the browser demonstration database
was compared using `scripts/domain_snapshot.php`: all fourteen table hashes and
row counts remained unchanged, including edited content, credentials and audit
records.

An additional restore run strengthened row comparison to MySQL `JSON_ARRAY` per
row, preserving NULL values, embedded tabs and line breaks rather than comparing
ambiguous raw tab-separated lines. All fourteen tables again matched, including
33 audit rows, 17 messages, 8 projects, 9 updates, 7 replies and 6 submissions.
Dump SHA-256: `a18ed36e7b97bbd3e8b735bfeca8fb248d3c54c4907068c8c136d7b9777133d0`.
The temporary restore database was removed by the test after successful comparison.

Actual Chromium **151.0.7922.34** was driven through Playwright against a separate
pristine demonstration database at `http://127.0.0.1:18081`. The recording performed
public navigation/live chart rendering, admin login, profile save, update
publication, logout, visitor pending-message creation, admin approval/reply, and
PDF download. The pending message was absent publicly until approved. Desktop
1440x960 and mobile 390x844 layouts had no horizontal document overflow, and the
browser emitted **zero page or console errors**. Screenshots were visually
inspected (not merely generated).

Artifacts:
- `data/team/星河工坊_操作演示.webm` (real silent automated recording).
- `docs/validation/screenshots/home.png`, `admin.png`, `mobile.png`.
- `scripts/record_walkthrough.cjs`; tooling dependency: Playwright plus Chromium.
- Local detailed browser report: `.cache/browser/report.json`.

The optional native infrastructure checks also passed Composer metadata/platform
requirements, PHP/Yii versions, and clean HTTP output/escaping. Compose 5.6.0 parsed
the updated configuration. WSL had neither a Docker executable nor engine socket;
no current application container execution is claimed. The CI workflow now runs
business HTTP journeys before and after restoration, and compares complete domain
fingerprints across container recreation and fresh-volume restoration.
