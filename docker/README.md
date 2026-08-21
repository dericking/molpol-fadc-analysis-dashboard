# Local test setup (Docker)

**Testing only** — not a production deploy. Run a full local stack
(MariaDB + Apache/PHP) so you can try site changes without touching a
live server. Names match the placeholders in `includes/dbconnect-template.php`
(`app_db` / `readonly_user` / `changeme`).

The main project README (`../README.md`) describes the site, schema
assumptions, and layouts. This file is compose, seed, ports, and logs.

## Prerequisites

- **Docker** + Docker Compose
- Project extracted so `docker/` sits next to `includes/` (see layout below)
- Host PHP is **not** required for the web UI (Apache runs in the container).
  Optional on the host only if you prefer to seed with host `php` against port 3307.

## Project layout

```
project-root/
├── index.php                 Browse runs / groups
├── detail_runs.php           Run analysis detail
├── detail_daq.php            DAQ config detail
├── detail_epics.php          EPICS snapshot detail
├── detail_groups.php         Group analysis detail
├── report.php                Simple filtered report + CSV
├── report_advanced.php       Advanced column-picker report
├── help_howto.php            Caretaker how-to
├── help_errors.php           Status-message help
├── README.md                 Site overview (parent of this file)
├── assets/
│   └── style.css
├── tools/
│   ├── init_site.sh              Deploy: copy dbconnect template → *.local.php
│   └── production_export.sh      Allowlist export from HEAD → webdir
├── includes/
│   ├── bootstrap.php
│   ├── config.php            Site knobs (no DB credentials)
│   ├── dbconnect-template.php
│   ├── schema.php            INFORMATION_SCHEMA helpers
│   ├── render_helpers.php
│   ├── descriptions_errors.php
│   ├── index_*.php / report_*.php
│   └── layouts/              Caretaker presentation only
│       ├── layout_navbar.php     Optional master top navbar
│       ├── layout_cards.php
│       ├── layout_tables.php
│       ├── layout_sections.php
│       ├── layout_run_summary.php
│       ├── layout_lookups.php
│       └── layout_report.php
└── docker/                   Local test stack (this folder)
    ├── docker-compose.yml
    ├── docker-compose.override.yml   (git-ignored; local port remaps)
    ├── Dockerfile            php:8.1-apache + pdo_mysql
    ├── seed_junk_data.php    Junk INSERT for UI testing
    ├── README.md             (this file)
    └── init/
        ├── 01_schema.sql     Don’s current tables (as of time of saving + Grouped Analysis)
        └── 02_privileges.sql SELECT-only for readonly_user
```

Compose mounts `project-root/` into the web container as `/var/www/html`.
All compose commands below must be run from `docker/` (where
`docker-compose.yml` lives).

## Quick start

```bash
cd docker

# Build the Apache/PHP image (first time / after Dockerfile changes) and start DB + web
docker compose up -d --build

# Wait until MariaDB is healthy, then seed junk data (as root inside the network)
docker compose exec -e SITE_DB_USER=root -e SITE_DB_PASS=changeme \
  -e SITE_DB_HOST=site-db-test -e SITE_DB_PORT=3306 \
  site-web php docker/seed_junk_data.php 30
```

Open [http://localhost:8080/index.php](http://localhost:8080/index.php).

Index filter bar placement: **Top Panel** (default) or **Side Panel** via the
subtitle links, or `?panel=top` / `?panel=side`. Default is
`default_panel` in `includes/config.php` (**side**).

Code edits on the host show up immediately (bind mount). Reload the browser;
no image rebuild needed for PHP/CSS changes.

If you previously used older compose names (`hamoller_*`) or only a DB
container, recreate once so init scripts and the web service are current:

```bash
cd docker
docker compose down -v
docker compose up -d --build
```

---

## Services

| Service | Role | Host access |
|---------|------|-------------|
| `site-db-test` | MariaDB 11, schema + SELECT-only user | `127.0.0.1:3307` → 3306 |
| `site-web` | Apache + PHP 8.1, site DocumentRoot | [http://localhost:8080](http://localhost:8080) |

Inside the compose network the web container talks to the DB as host
`site-db-test` port `3306` (set via `SITE_DB_*` in `docker-compose.yml`).

## 1. Start a testing stack (if needed)

```bash
cd docker
docker compose up -d --build
```

First DB start runs `init/01_schema.sql` then `init/02_privileges.sql` (empty
volume only). After editing init SQL:

```bash
docker compose down -v
docker compose up -d --build
```

Confirm MariaDB:

```bash
docker compose logs -f site-db-test   # ctrl-C after "ready for connections" / healthy
```

## 2. Seed junk data (optional)

From `docker/`, using PHP **inside** `site-web` (recommended — no host PHP):

```bash
docker compose exec -e SITE_DB_USER=root -e SITE_DB_PASS=changeme \
  -e SITE_DB_HOST=site-db-test -e SITE_DB_PORT=3306 \
  site-web php docker/seed_junk_data.php 30
```

`30` is the run-count target; omit for the default. Seeding needs INSERT
(root). The site itself uses `readonly_user`.

Or from the host (needs host PHP + `pdo_mysql`), against the published port:

```bash
cd ..   # project root
SITE_DB_HOST=127.0.0.1 SITE_DB_PORT=3307 \
SITE_DB_USER=root SITE_DB_PASS=changeme \
  php docker/seed_junk_data.php 30
```

## 3. Use the site and read Apache logs

Browse [http://localhost:8080/index.php](http://localhost:8080/index.php).

Apache in `site-web` logs to the container’s stdout/stderr. Follow them while
testing:

```bash
cd docker

# Apache + PHP (access lines, PHP warnings, dbconnect error_log(), etc.)
docker compose logs -f site-web

# Database only
docker compose logs -f site-db-test

# Everything
docker compose logs -f
```

Last N lines without following:

```bash
docker compose logs --tail=100 site-web
```

Inside the container (same files Apache writes when not only on stdio):

```bash
docker compose exec site-web tail -f /var/log/apache2/error.log
docker compose exec site-web tail -f /var/log/apache2/access.log
```

(If a path is missing, prefer `docker compose logs -f site-web` — the official
PHP Apache image usually mirrors those streams to compose logs.)

## 4. Database credentials (placeholders)

| Variable | Value in compose (web → DB) |
|----------|-----------------------------|
| `SITE_DB_HOST` | `site-db-test` |
| `SITE_DB_PORT` | `3306` |
| `SITE_DB_NAME` | `app_db` |
| `SITE_DB_USER` | `readonly_user` |
| `SITE_DB_PASS` | `changeme` |

Host tools use `127.0.0.1` and port `3307` instead of `site-db-test` / `3306`.
Use **`127.0.0.1`**, not `localhost`, for host PDO (socket vs TCP gotcha).

Site knobs (`default_view`, `default_layout`, `default_panel`, plot paths,
`row_cap`, `site_title`) live in `includes/config.php`. Caretaker list/detail
layouts are the `includes/layouts/layout_*.php` files.

## Tearing down

From `docker/`:

```bash
docker compose down       # stop DB + Apache, keep DB volume
docker compose down -v    # also wipe DB data — next up re-runs init scripts
```
