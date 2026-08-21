# Møller Polarimetry Run Log

Read-only PHP dashboard over a MariaDB run-log / DAQ database for the
**Møller polarimeter** (not the MOLLER experiment).

The live schema is owned outside this site. This code only **reads** it.
New columns are meant to appear without a PHP change; presentation
(which card, which table column, which modal row) is maintained in
layout files.

PHP 8.1, no framework, PDO with prepared statements, one shared
stylesheet (`assets/style.css`). No JavaScript: Run Info / Group Info
are CSS `:target` modals.

---

## Data model

| Table | Key | Role |
|-------|-----|------|
| `Run_info` | `run_number` | Parent run record |
| `DAQ_config` | `run_number` | Per-run DAQ settings |
| `EPICS_data` | `run_number` | Per-run EPICS snapshot |
| `Analysis` | `run_number` | Per-run (prompt) analysis |
| `Grouped_Analysis` | `group_number` | Group / final analysis landing spot |

`Run_info.run_group` points a run at a group. A group page can show
Grouped_Analysis, member runs, or both. Plots are files on disk under
`{plots_base}/{run_number}/` or `{plots_base}/{group_number}/`.

Type and quality on runs and groups are **codes** (`POLARIZATION`,
`GOOD`) with English in shared lookup tables
`run_type_lookup` and `run_quality_lookup` (`code`, `display_label`).
The site never hardcodes those labels. Filter SQL and quality colors
use the code; cards and the type dropdown show `display_label`.
`PENDING` is the unset-quality code (not `UNDETERMINED`). `MIXED` is a
**type** (groups whose member runs are not all the same type). `JUNK`
and `TEST` are additional quality/type codes.

---

## Schema assumptions

The site introspects `INFORMATION_SCHEMA` at request time
(`get_table_columns()`). **Adding** a column with a `COLUMN_COMMENT` is
the happy path: it shows up using that comment as the label (and EPICS
`[PV: …]` when present). Unmatched columns land in **Other** /
**Unallocated Sections**, not vanish.

**Comments are load-bearing.** Do not strip `COLUMN_COMMENT`s; the UI
has no second copy of those labels.

### Identity (engine SQL)

These names are used in queries, not only in layouts. Renaming or
dropping them needs a site change (or the page 500s / cannot join):

- Tables listed above
- PKs `run_number`, `group_number`
- Membership `Run_info.run_group`

Also expected on the list pages (if missing, the index **warns** and
keeps going):

- `run_type` / `group_type` — type dropdown and filter (codes; labels from `run_type_lookup` when present)
- `run_experiment` — experiment dropdown (distinct values from `Run_info`; groups match if any member run has that experiment)
- `run_start` / `group_start` — date headings and from/to filter (else one **Unknown date**
  bucket with an ⓘ; date filter disabled)

`last_updated` is shown on detail headers when present; if absent the
header shows `—`.

### `*_err` pairing

On detail cards, if both `foo` and `foo_err` exist, they render as one
row: `value ± error`. The `_err` row is not listed separately. An
orphan `*_err` with no base column still shows alone. A different error
suffix (`foo_uncertainty`) is two rows, not a crash.

### New tables

A new table still needs a page (or a key in `section_view_table_map()`).
Introspection does not invent pages.

---

## Layout-driven maintenance

Caretakers change **what appears where** under `includes/layouts/`.
**Touch** those files for presentation; **do not** edit other `includes/*.php`,
page PHP, or CSS for routine “what shows where” tweaks (CSS only when a
layout needs a class already in the design system).
Header comments in the layout files are the instructions. Do not put DB
credentials in layout files. Do not put URLs in most layouts (cards/tables
use `'link' => 'run'|'group'`); the exception is
`layout_navbar.php`, which is specifically for site-wide hrefs.

| File | What it controls |
|------|------------------|
| `includes/layouts/layout_navbar.php` | Optional **master top navbar** (`links` with `href` + `label`). Empty `links` → bar hidden. Colors via `--site-nav-*` in `assets/style.css`. |
| `includes/layouts/layout_cards.php` | Index / group-member **cards**. Optional `'link' => 'run'` or `'group'` on a cell (only that cell is a link). |
| `includes/layouts/layout_tables.php` | Index / group-member **tables**. Same `link` keys. |
| `includes/layouts/layout_report.php` | Report page **column catalog** + defaults (picker / CSV). |
| `includes/layouts/layout_run_summary.php` | **Run Info** and **Group Info** modal rows + comment footer. |
| `includes/layouts/layout_lookups.php` | Row column → lookup table, and quality **code** → CSS slug (`junk`, `pending`, …). Not display labels. |
| `includes/layouts/layout_sections.php` | Detail-page **classifiers** (which column → which section), `exclude`, featured rows, named card bands (`main`, `other`, …). |

Classifier unmatched columns still render under **Unallocated Sections**
(rows of at most four). Hide leftovers from that band only via
`ignore_sections` (they still show if also listed in featured/layouts).

DAQ / Analysis / EPICS **groupings are yours to define** in
`includes/layouts/layout_sections.php` when you are ready. Prefix rules (Analysis /
Grouped_Analysis) and regex lists (EPICS / DAQ) are the mechanism;
section titles in `featured` / `layouts` must match classifier `section`
strings exactly.

Site knobs that are **not** layouts: `includes/config.php` (title, index
defaults, plot paths, browse `row_cap`, report `report_row_cap`).

---

## Status messages and help

Stay-on-page empty states and warnings use `render_status_message()`.
The circled **i** opens `help_errors.php?key=…` in a new tab.

- `includes/descriptions_errors.php` — catalog (`summary`, `title`,
  `body`, `fix`). `body` / `fix` are trusted HTML edited only in that
  file.
- `help_errors.php` — one topic with `?key=`; **no query** lists every
  topic with a left nav.

Examples: no Analysis row yet, no member runs, plot directory missing,
index type/date column missing, `run_group` missing on the group page.

---

## Local verification

With the Docker stack up and seeded (see `docker/README.md`):

```bash
php tests/run.php                                 # pure helpers, no Docker
./tests/smoke.sh http://127.0.0.1:8090            # HTTP status + no leaked fatals
./tests/schema_drift.sh http://127.0.0.1:8090     # ALTER TABLE degrade checks (+ reseed)
```

Notes and what was exercised by hand: `tests/MANUAL.md`.
Default smoke/drift fixture run is **20000** / group **1** (junk seed).
Override with `SMOKE_RUN` / `SMOKE_GROUP` if needed.

---

## Deploy / setup

Single web server: PHP with PDO MySQL + the MariaDB this site reads.
There is no build step.

**Ship only the site, not the repo.** Design posture: assume an unhardened
host that does not honor `.htaccess` and has directory listings on, so
anything in the tree is reachable. Do not clone the repository into the
web directory. Export from a clean commit with the allowlist script:

```bash
# Dry run (default) — shows what would ship and any stale files in webdir
./tools/production_export.sh /path/to/webdir

# Write the export, then (optional) prune stale files after reviewing the list
./tools/production_export.sh /path/to/webdir --apply
./tools/production_export.sh /path/to/webdir --apply --prune

# After apply, optional live probes (needs a reachable site URL)
./tools/production_export.sh /path/to/webdir --apply --url https://SITE
```

Only paths listed in `SITE_PATHS` inside that script are archived from
`HEAD` (pages, `assets/`, `includes/`, `.htaccess`, and `tools/init_site.sh`).
`.cursor/`, `docker/`, `*.md`, tests, and the export script itself stay out.
The credentials file `includes/dbconnect-*.local.php` is never in the repo
and is never deleted by the export.
After any deploy that overwrites `includes/bootstrap.php`, re-run
`./tools/init_site.sh --relink` in the web directory (the export script
reminds you).

**Database — first-time setup**

`includes/dbconnect-template.php` is the tracked template. It holds
placeholders only (`127.0.0.1`, `app_db`, `readonly_user`, `changeme`).
Real credentials never go in that file.

On the web server, from the site root:

```bash
./tools/init_site.sh
```

It copies the template to `includes/dbconnect-<random>.local.php`
(git-ignored), writes the host / name / user / password you type, and
repoints `bootstrap.php` at that copy. The random name is not stored
anywhere in the repo, so a later `git archive` leaves it alone.

Use a **SELECT-only** account, preferably granted only from the web
server host. From the host, use **`127.0.0.1`**, not `localhost` — PHP’s
MySQL driver treats `localhost` as a Unix socket and ignores the port.
A real hostname (a separate DB server) is unaffected.

The Docker test stack still uses `SITE_DB_*` environment variables and
does not need this script.

**Plots**

Edit `run_plots_web_base` / `group_plots_web_base` and, if needed,
`*_plots_fs_base` in `includes/config.php`. Site-relative web paths can
omit `fs_base` (DOCUMENT_ROOT + web path). Absolute plot URLs need an
explicit filesystem base.

---

## Local testing

`docker/` is a **test-only** MariaDB + Apache/PHP stack, plus
`docker/seed_junk_data.php` (quick fake runs). It is not how production
is meant to run. See `docker/README.md`.

Automated checks (after the stack is up and seeded): `php tests/run.php`,
`./tests/smoke.sh`, `./tests/schema_drift.sh` — see **Local verification**
above and `tests/MANUAL.md`.

### Scale snapshot (local Docker, 2026-08-21)

One-off check against `http://127.0.0.1:8090` with **20 000** `Run_info`
rows (matching `Analysis` / `DAQ_config` / `EPICS_data` PK rows and
**10 000** `Grouped_Analysis` rows). Caps unchanged (`row_cap` 300,
`report_row_cap` 2000). Warm once, then five `curl` timings; table is
**median** wall time. DB restored to the normal ~30-run junk seed
afterward. No load-test script is kept in the tree.

| URL | Median s | Body (approx) |
|-----|---------:|--------------:|
| `/index.php` | 0.013 | 138 k |
| `/index.php?view=groups` | 0.014 | 106 k |
| `/index.php?layout=cards` | 0.013 | 123 k |
| `/index.php?type=POLARIZATION` | 0.014 | 143 k |
| `/index.php?from=2025-01-01&to=2025-06-30` | 0.021 | 138 k |
| `/report.php` | 0.038 | 1.3 M |
| `/report.php?view=groups` | 0.027 | 839 k |
| `/report.php?format=csv` | 0.026 | 174 k |
| `/report_advanced.php` | 0.036 | 1.4 M |
| `/detail_runs.php?run=20000` | 0.003 | 4 k |
| `/detail_daq.php?run=20000` | 0.002 | 5 k |
| `/detail_epics.php?run=20000` | 0.003 | 10 k |
| `/detail_groups.php?group=1` | 0.011 | 5 k |
| `/help_howto.php` | 0.001 | 42 k |

On this laptop Docker path, capped browse/report stayed under ~50 ms at
20 k runs. Heavier paths were date-range index and full report HTML.
Detail pages were ~2–3 ms except group detail (~11 ms). Re-check ad hoc
if production row counts or joins differ a lot.
---

## Pages (entry points)

| Page | Role |
|------|------|
| `index.php` | Run/group list (table or cards; top or side filters) |
| `report.php` | Simple filtered run/group report (layout defaults) + CSV |
| `report_advanced.php` | Same report with Available \| Selected column picker + CSV |
| `detail_runs.php` | Analysis + plots; Run Info modal |
| `detail_epics.php` | EPICS_data |
| `detail_daq.php` | DAQ_config (canonical thin detail template) |
| `detail_groups.php` | Grouped_Analysis + member runs + plots; Group Info modal |
| `help_errors.php` | Status-help catalog |
| `help_howto.php` | Caretaker how-to (schema changes + layouts) |

Includes worth knowing: `bootstrap.php` (every page), `schema.php`,
`render_helpers.php` (barrel for `helpers_*.php`), `index_query.php` / `index_filters.php` /
`index_results.php`, `report_query.php`, `report_filters.php`, `report_results.php`, `includes/layouts/layout_report.php`.

---

## What to edit for what

| Goal | Where |
|------|--------|
| Card / table / modal / detail grouping | `includes/layouts/` |
| Site title, index defaults, plot paths | `includes/config.php` |
| Help text for ⓘ | `includes/descriptions_errors.php` |
| Live DB connection | `./tools/init_site.sh` (writes a git-ignored `*.local.php`; do not edit the template) |
| CSS | `assets/style.css` only |
| Page titles / empty-copy strings | The `Page copy` block at the top of each detail page |
