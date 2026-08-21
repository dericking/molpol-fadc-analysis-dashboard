#!/bin/bash
# =============================================================================
# tests/smoke.sh — HTTP smoke against the local Docker (or any) site
# =============================================================================
#
#   ./tests/smoke.sh
#   ./tests/smoke.sh http://127.0.0.1:8090
#   SMOKE_RUN=20000 SMOKE_GROUP=1 ./tests/smoke.sh
#
# Expects a seeded DB (docker/seed_junk_data.php). Default fixture IDs match
# that seed (runs from 20000). Default BASE is the compose override port 8090.
# =============================================================================

set -u

BASE="${1:-http://127.0.0.1:8090}"
BASE="${BASE%/}"
RUN="${SMOKE_RUN:-20000}"
GROUP="${SMOKE_GROUP:-1}"
BODY="$(mktemp)"
fail=0

cleanup() { rm -f "$BODY"; }
trap cleanup EXIT

check() {
    # check <expected_http_code> <path>
    local want="$1" path="$2"
    local code
    code="$(curl -sS -o "$BODY" -w '%{http_code}' --max-time 15 "${BASE}/${path}" 2>/dev/null || echo 000)"
    if [ "$code" != "$want" ]; then
        echo "FAIL want $want got $code  /$path"
        fail=$((fail + 1))
        return
    fi
    if grep -qE 'Fatal error|/var/www/|PDOException|Uncaught ' "$BODY"; then
        echo "FAIL leaked diagnostics in body  /$path"
        fail=$((fail + 1))
        return
    fi
    echo "ok $want /$path"
}

echo "Smoke against ${BASE} (run=${RUN} group=${GROUP})"
echo

# Happy paths
for p in \
    index.php \
    report.php \
    report_advanced.php \
    help_howto.php \
    help_errors.php \
    "index.php?view=groups" \
    "index.php?panel=top" \
    "index.php?layout=cards" \
    "detail_runs.php?run=${RUN}" \
    "detail_epics.php?run=${RUN}" \
    "detail_daq.php?run=${RUN}" \
    "detail_groups.php?group=${GROUP}" \
    "report.php?format=csv"
do
    check 200 "$p"
done

# Malformed ids → 400 (P0-2 min_range)
check 400 "detail_runs.php?run=abc"
check 400 "detail_runs.php?run=-5"
check 400 "detail_runs.php?run=0"
check 400 "detail_groups.php?group=0"

# Missing row → 404
check 404 "detail_runs.php?run=999999999"

# Soft degradation (still 200)
for p in \
    "index.php?from=notadate" \
    "index.php?view=garbage" \
    "report.php?cols[]=bogus_column" \
    "report.php?cols[]=r.%20FROM%20x--"
do
    check 200 "$p"
done

echo
if [ "$fail" -eq 0 ]; then
    echo "PASS"
    exit 0
fi
echo "$fail failure(s)"
exit 1
