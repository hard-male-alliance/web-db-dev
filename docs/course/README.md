# Course submission sources and reproducible PDFs

These six Chinese documents satisfy the six deliverable categories in
`../internet-database-development-assignments.md`. The generated outputs are in
`../../data/team/`, including a landscape presentation PDF (the assignment permits
PDF or PPT). The web application downloads those exact files through the
`submissions` records, rather than exposing the repository as static files.

## Build

From the repository root, using Python with ReportLab installed:

```sh
python scripts/build_submissions.py
# Optional: select an installed Simplified Chinese TrueType font explicitly.
python scripts/build_submissions.py --font /path/to/chinese-font.ttf
```

Only ReportLab is needed for generation. The PDF sources use a deliberately small
Markdown subset: headings, paragraphs, pipe tables, `@page`, `@diagram` / `@end`,
`@image`, and `@dictionary`. The dictionary reads `data/schema.sql` directly, preserving all
14 tables, column types (including ENUM), defaults, indexes, checks and foreign
keys. `data/install.sql` is the actual mysqldump hand-in, not the repeat-safe startup
schema. Do not replace the startup initializer with that destructive dump.

The builder uses an installed Chinese TrueType font where available (SimHei on
Windows); its glyph subset is embedded. If none is found, ReportLab's standard
STSong CJK font is used, which depends on PDF viewer CJK support. To reproduce the
embedded-font result across platforms, supply a properly licensed installed TTF
with `--font`; no platform font binary is copied into this repository.

## Content coverage

| Source | Deliverable coverage |
| --- | --- |
| `01-需求文档.md` | Product goals, roles, functional acceptance, data and nonfunctional constraints |
| `02-设计文档.md` | Module and workflow vector diagrams, relationship cardinalities, all-field SQL dictionary |
| `03-实现文档.md` | Real implementation, executed HTTP/restore evidence, browser screenshot and attribution boundary |
| `04-用户手册.md` | Visitor browsing, download, message submission, member/admin operations and troubleshooting |
| `05-部署文档.md` | Canonical startup, backup, isolated restoration, persistence, destructive-operation warnings |
| `06-项目展示.md` | Nine landscape slides, real screenshot, journey and separate table/code/document workload plans |

## Submission identity and remaining human actions

The team is **星河工坊**. `DEMO01`, `DEMO02`, `DEMO03` are explicitly demo identifiers;
seed member names 林星, 陈河 and 周远 are fictional replaceable demo profiles, not
claims about real students. The workload tables are **proposals**, not evidence
that anyone authored the corresponding code. Before formal submission:

- [ ] Replace demo names and IDs with real, authorized student identities.
- [ ] Replace the builder `IDS`, output filenames and database submission records
      together; rebuild PDFs and recheck all six downloads.
- [ ] Confirm actual table/code/document work allocation, including each student's
      own model, view and controller files, supported by real Git/task records.
- [ ] Add genuine team collaboration evidence if requested; do not manufacture
      screenshots, authorship histories or work-hour records.
- [ ] Add real student narration to the existing silent automated walkthrough,
      contact the teaching assistant, confirm the actual
      deadline year and submit via the course's required channel.

These human-only actions cannot be completed by inventing names or retroactively
assigning generated implementation to students.

## PDF QA

Generation was exercised with the bundled Python / ReportLab runtime on Windows,
using embedded `C:/Windows/Fonts/simhei.ttf`. All six PDFs were rendered using
Poppler `pdftoppm`, inspected as contact sheets and individual pages, and their
text extracted with pypdf. Review images are disposable under `.cache/pdf-review/`.
The presentation uses larger typography than the portrait technical reports;
diagrams remain vector objects. The dictionary preserves the schema rather than
an independently maintained model. Re-run rendering after any text/schema change.

Final build page counts: requirements 3, design 9, implementation 3, user manual 2,
deployment 2, presentation 9 (28 pages total). An independent SQL-column walk
matched the dictionary's 83 fields across 14 tables in order, including ENUM and
constraint rows. Text checks confirmed demo IDs in every PDF, the actual runtime
evidence in the implementation report and all schema table names in the design.

Example review commands (requires Poppler and pypdf):

```sh
pdftoppm -scale-to 1400 -png 'data/team/星河工坊_设计文档(DEMO01_DEMO02_DEMO03).pdf' '.cache/pdf-review/design'
python -c "from pypdf import PdfReader; r=PdfReader('data/team/星河工坊_设计文档(DEMO01_DEMO02_DEMO03).pdf'); print(len(r.pages)); print(''.join(p.extract_text() for p in r.pages))"
```

The deployment walkthrough intentionally tests a distinct Compose project and
port. It must not delete or restore over the original demo volume. The final
runtime validation record is maintained by the integrator; PDF generation does
not imply that every documented deployment path was executed on this host.
