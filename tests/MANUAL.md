# Manual testing notes

Honest record of verification performed against this site. Automated coverage
lives in `tests/smoke.sh`, `tests/run.php`, and `tests/schema_drift.sh`.

## Environment

- Local Docker stack: `docker/` (`site-db-test` on host **3307**, `site-web` often
  on **8090** via `docker-compose.override.yml`; compose default web port is 8080).
- Seed: `docker/seed_junk_data.php` (default run numbers from **20000**).
- Host PHP against DB is optional; smoke/drift prefer the web container URL.

## What was exercised (sessions through 2026-08)

| Area | What | Expected |
|------|------|----------|
| Index | runs/groups, table/cards, top/side panel | 200, filters usable |
| Detail | runs / DAQ / EPICS / groups for seeded ids | 200, sections render |
| Report | simple + advanced, CSV | 200 / CSV download |
| Help | howto + status catalog | 200 |
| Bad ids | `run=abc`, `run=0`, `run=-5` | **400** (after P0-2) |
| Missing id | `run=999999999` | **404** |
| Soft bad input | bad `from=`, unknown `view=`, bogus `cols[]` | **200**, no fatals |
| Bootstrap | forced errors historically | no `/var/www/` or PDO text in body |
| Export | `tools/production_export.sh` dry-run/apply in a clean clone | allowlist only; 36 files |
| Quality slug | `NOT_GOOD` / odd codes | `unknown` class, not pending |
| Schema drift | ADD/DROP/RENAME via `tests/schema_drift.sh` | 200 + status warnings |

## Schema mutations

Automated in `tests/schema_drift.sh` (undo + reseed). Not relied on as a
one-off manual `ALTER` against a shared long-lived volume without restore.

## Gaps / not claimed

- No full browser UI checklist (screenshots, a11y).
- No load test / 20k-run baseline (P2-3).
- Production host `.htaccess` effectiveness depends on AllowOverride (see
  `.htaccess` header and export `--url` probes).
- Analysis classifier vs Don’s names may still leave some fields in Other —
  that is layout work, not covered as a “must be empty Other” assertion.

## How to re-run

```bash
# Units (no Docker required)
php tests/run.php

# HTTP smoke (Docker web up + seeded)
./tests/smoke.sh http://127.0.0.1:8090

# Schema-drift (Docker DB+web; mutates then restores/reseeds)
./tests/schema_drift.sh http://127.0.0.1:8090
```
