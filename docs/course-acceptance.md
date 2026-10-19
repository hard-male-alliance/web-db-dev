# Course requirement delivery map

Date: 2026-10-19 (Asia/Singapore). Scope: repository implementation and reproducible
demonstration materials, not a certification of human coursework contributions.

| Assignment requirement | Delivered implementation | Evidence / remaining action |
| --- | --- | --- |
| Named team | 星河工坊 | Clearly marked demonstration member identities |
| Public team/member website | `frontend/controllers`, `frontend/views`, local CSS/JS | Real desktop/mobile browser journey and HTTP acceptance |
| Visitor messages | Pending creation, approved-only public list, admin moderation/reply | Separate visitor/admin sessions; escaping and private-email checks |
| Back-office editing/publishing | `backend/controllers`, role-scoped forms | Login, own profile, assigned projects, own updates; administrator team/member management |
| More than ten tables | 14 relational tables, indexes, foreign keys, CHECK constraints | `data/schema.sql`; restored real MySQL export `data/install.sql` |
| Dynamic chart bonus | Accessible canvas visualization backed by live statistics JSON | Browser checks live chart label/data and zero-value states |
| Version control and management tools | Git; repository task-board convention and traceability template | Real team must record actual tasks/ownership rather than treating demo allocation as history |
| Naming and PHP headers | Consistent MVC names; English file-level headers | All application PHP files linted; no source exposed by router |
| SQL in `/data/install.sql` | Literal MySQL 8.4.11 dump from a pristine demonstration DB | Destructive restore warning; safe startup deliberately uses separate `schema.sql` |
| Six submissions in `/data/team/` | Requirements, design, implementation, user manual, deployment, presentation PDFs | Editable sources `docs/course/`; reproducible `scripts/build_submissions.py` |
| Database dictionary and diagrams | 14-table field dictionary generated from canonical schema; module/flow/entity diagrams | Final PDFs rendered and visually checked |
| Download links | Six submission cards and guarded attachment endpoint | All six return real PDF bytes and attachment headers |
| One-click deployment bonus | `python scripts/dev.py up` | Builds/waits/initializes/checks; current Compose configuration parsed successfully |
| Restoration procedure | Backup/import commands, isolated Compose project walkthrough, executable fresh-DB restore | All 14 tables compared after actual user edits; CI also fingerprints all rows across container replacement/restore |
| Recorded walkthrough | `data/team/星河工坊_操作演示.webm` | Real browser recording, silent/automated; not a substitute for students' oral explanation |
| Per-member MVC contribution | Explicit contribution-registration template in submissions | Requires actual students and genuine commits; cannot be invented by automation |
| Member workload attribution | Proposed DEMO01/02/03 allocation disclosed in implementation/presentation | Replace IDs/names and confirm actual database/code/document workload before hand-in |
| Contact TA and send final materials | Instructions and deadline uncertainty documented | Human action; no email or contact made by this task |

## Acceptance artifacts

- `docs/validation/application.md`: native Windows + actual MySQL HTTP and restore evidence.
- `docs/review/application-review.md`: independent review and five resolved defects.
- `docs/validation/screenshots/`: actual public, administrator and mobile rendering.
- `scripts/record_walkthrough.cjs`: reproducible browser publication/moderation/download recording.
- `tests/application_journey.py`: six multi-assertion HTTP scenarios, including authorization and input boundaries.
- `tests/application_restore.py`: actual dump -> new DB -> JSON-row equality, including NULL/newline preservation.
- `scripts/domain_snapshot.php`: consistent fingerprints of all domain rows for CI persistence/restore comparisons.

The current application Docker image has not been executed on this host because
there is no Docker engine. Configuration parsing and source review do not replace
that execution. Existing historical Docker CI results apply only to the earlier
infrastructure commits; the expanded application workflow is ready for a new run.
Do not infer public-hosting readiness from local demo acceptance.
