#!/bin/bash
# =============================================================================
# tests/schema_drift.sh — prove graceful degradation under ALTER TABLE
# =============================================================================
#
# Mutates the Docker test DB temporarily, curls the site, undoes each change.
# Requires: docker compose stack up (site-db-test + site-web), seeded data.
#
#   ./tests/schema_drift.sh
#   ./tests/schema_drift.sh http://127.0.0.1:8090
#
# If an undo fails, recreate: cd docker && docker compose down -v && up -d --build
# then reseed (see docker/README.md).
# =============================================================================

set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_DIR="$ROOT/docker"
BASE="${1:-http://127.0.0.1:8090}"
BASE="${BASE%/}"
RUN="${SMOKE_RUN:-20000}"
GROUP="${SMOKE_GROUP:-1}"
BODY="$(mktemp)"
fail=0

cleanup() { rm -f "$BODY"; }
trap cleanup EXIT

die() { echo "error: $*" >&2; exit 1; }

[ -d "$COMPOSE_DIR" ] || die "missing $COMPOSE_DIR"
command -v docker >/dev/null || die "docker not found"
command -v curl >/dev/null || die "curl not found"

cd "$COMPOSE_DIR" || die "cannot cd $COMPOSE_DIR"

db() {
    # db <sql>
    docker compose exec -T site-db-test mariadb -uroot -pchangeme app_db -e "$1" 2>/dev/null
}

fetch() {
    # fetch <path> → sets BODY, echoes http code
    curl -sS -o "$BODY" -w '%{http_code}' --max-time 20 "${BASE}/$1" 2>/dev/null || echo 000
}

assert_code() {
    local want="$1" path="$2" label="$3"
    local code
    code="$(fetch "$path")"
    if [ "$code" != "$want" ]; then
        echo "FAIL [$label] want HTTP $want got $code  /$path"
        fail=$((fail + 1))
        return 1
    fi
    if grep -qE 'Fatal error|/var/www/|PDOException|Uncaught ' "$BODY"; then
        echo "FAIL [$label] leaked diagnostics  /$path"
        fail=$((fail + 1))
        return 1
    fi
    return 0
}

assert_body() {
    local pattern="$1" label="$2"
    if ! grep -qE "$pattern" "$BODY"; then
        echo "FAIL [$label] body missing /$pattern/"
        fail=$((fail + 1))
        return 1
    fi
    return 0
}

echo "Schema-drift against ${BASE} (compose: $COMPOSE_DIR)"
echo "Fixture run=${RUN} group=${GROUP}"
echo

# Sanity
code="$(fetch "index.php")"
[ "$code" = "200" ] || die "site not reachable at ${BASE}/index.php (got $code)"
db "SELECT 1" >/dev/null || die "cannot query site-db-test"

# --- 1. New Analysis column with COMMENT appears as label -------------------
echo "1. ADD COLUMN with COMMENT → detail label"
db "ALTER TABLE Analysis ADD COLUMN smoke_labeled FLOAT NULL COMMENT 'Smoke labeled field'"
if assert_code 200 "detail_runs.php?run=${RUN}" "add-commented-column"; then
    assert_body 'Smoke labeled field' "add-commented-column label" && echo "ok  labeled column visible"
fi
db "ALTER TABLE Analysis DROP COLUMN smoke_labeled"

# --- 2. New column without COMMENT → humanized, not vanished ----------------
echo "2. ADD COLUMN without COMMENT → humanized name"
db "ALTER TABLE Analysis ADD COLUMN smoke_unlabeled_col FLOAT NULL"
if assert_code 200 "detail_runs.php?run=${RUN}" "add-bare-column"; then
    assert_body 'Smoke Unlabeled Col' "add-bare-column humanize" && echo "ok  humanized column visible"
fi
db "ALTER TABLE Analysis DROP COLUMN smoke_unlabeled_col"

# --- 3. Drop run_type → index warns, still 200 ------------------------------
echo "3. DROP Run_info.run_type → index warning"
db "ALTER TABLE Run_info DROP FOREIGN KEY fk_run_info_type"
db "ALTER TABLE Run_info DROP COLUMN run_type"
if assert_code 200 "index.php" "drop-run_type"; then
    assert_body 'index_missing_type_column|run_type is missing' "drop-run_type warning" \
        && echo "ok  type-column warning"
fi
# Restore
db "ALTER TABLE Run_info ADD COLUMN run_type VARCHAR(32) NOT NULL DEFAULT 'OTHER'"
db "ALTER TABLE Run_info ADD CONSTRAINT fk_run_info_type
    FOREIGN KEY (run_type) REFERENCES run_type_lookup(code)
    ON UPDATE CASCADE ON DELETE RESTRICT"

# --- 4. Rename report catalog column → soft WARNING -------------------------
echo "4. RENAME Analysis.leftrate → report soft warning"
db "ALTER TABLE Analysis CHANGE COLUMN leftrate leftrate_renamed_smoke FLOAT NULL"
if assert_code 200 "report.php" "rename-leftrate"; then
    assert_body 'leftrate|layout_report' "rename-leftrate warning" \
        && echo "ok  report layout warning"
fi
db "ALTER TABLE Analysis CHANGE COLUMN leftrate_renamed_smoke leftrate FLOAT NULL
    COMMENT 'Left detector rate (Hz)'"

# --- 5. Hide run_group (rename) → group page warns; values preserved --------
echo "5. RENAME Run_info.run_group away → group page warning"
db "ALTER TABLE Run_info CHANGE COLUMN run_group run_group_drift_hide INT UNSIGNED NULL"
if assert_code 200 "detail_groups.php?group=${GROUP}" "hide-run_group"; then
    assert_body 'group_missing_run_group_column|run_group is missing' "hide-run_group warning" \
        && echo "ok  run_group warning"
fi
db "ALTER TABLE Run_info CHANGE COLUMN run_group_drift_hide run_group INT UNSIGNED NULL"

# --- restore run_type labels (DROP lost them) --------------------------------
echo "Truncating + reseeding so run_type / membership match a known-good seed ..."
db "SET FOREIGN_KEY_CHECKS=0;
    TRUNCATE TABLE Analysis;
    TRUNCATE TABLE DAQ_config;
    TRUNCATE TABLE EPICS_data;
    TRUNCATE TABLE Grouped_Analysis;
    TRUNCATE TABLE Run_info;
    SET FOREIGN_KEY_CHECKS=1;"
if docker compose exec -T \
    -e SITE_DB_USER=root -e SITE_DB_PASS=changeme \
    -e SITE_DB_HOST=site-db-test -e SITE_DB_PORT=3306 \
    site-web php docker/seed_junk_data.php 30; then
    echo "ok  reseeded"
else
    echo "WARN  reseed failed — run seed manually (see docker/README.md)"
fi

echo
if [ "$fail" -eq 0 ]; then
    echo "PASS"
    exit 0
fi
echo "$fail failure(s)"
echo "If the schema looks wrong: cd docker && docker compose down -v && docker compose up -d --build && reseed"
exit 1
