#!/bin/bash
# =============================================================================
# production_export.sh — deploy the site, and prove the deploy is sound
# =============================================================================
#
# Copies only site content from the committed HEAD into the web directory,
# then runs the checks that init_site.sh documents but cannot perform.
#
#   ./tools/production_export.sh /path/to/webdir                 # dry run
#   ./tools/production_export.sh /path/to/webdir --apply         # write it
#   ./tools/production_export.sh /path/to/webdir --apply --url https://SITE
#
# Dry run is the default because a deploy is not reversible. Nothing is
# written without --apply.
#
# Why an allowlist rather than an exclude list: SITE_PATHS below names what
# the web server is allowed to have. A file added to the repo later is not
# deployed until it is named here. The failure mode of a deny list is a new
# directory silently reaching the web root; the failure mode of an allow list
# is a missing file, which is loud and harmless.
#
# Why nothing is ever deleted: includes/dbconnect-<random>.local.php holds the
# credentials, exists only in the web directory, and is not in the repo. Any
# form of mirror-with-delete removes it and every page returns 500. Stale
# files are reported instead, and removed only with --prune after you look at
# the list.
#
# What this does NOT do: it does not run init_site.sh. Deploy overwrites
# bootstrap.php, so run `./tools/init_site.sh --relink` in the web directory
# afterwards. This script reminds you.
# =============================================================================

set -u

# This file lives in tools/; repo root is one level up.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# --- what the web server is allowed to have ----------------------------------
# Anything not named here stays out: .cursor/, docker/, *.md, tests/, and this
# script itself. tools/init_site.sh IS deployed — it resolves its own paths
# relative to its location, so it has to sit inside the tree it repairs.
SITE_PATHS=(
    .htaccess
    index.php
    report.php
    report_advanced.php
    detail_runs.php
    detail_epics.php
    detail_daq.php
    detail_groups.php
    help_howto.php
    help_errors.php
    assets
    includes
    tools/init_site.sh
    tools/index.html
)

APPLY=0
PRUNE=0
URL=""
WEBDIR=""

die() { echo "error: $*" >&2; exit 1; }

# List paths under $1 relative to that root (portable; no GNU find -printf).
list_rel_files() {
    (cd "$1" && find . -type f | sed 's|^\./||' | sort)
}

# --- arguments ---------------------------------------------------------------
while [ "$#" -gt 0 ]; do
    case "$1" in
        --apply) APPLY=1 ;;
        --prune) PRUNE=1 ;;
        --url)   shift; URL="${1:-}"; [ -n "$URL" ] || die "--url needs a value" ;;
        -*)      die "unknown option: $1" ;;
        *)       [ -z "$WEBDIR" ] || die "more than one target given"; WEBDIR="$1" ;;
    esac
    shift
done

[ -n "$WEBDIR" ] || die "usage: $0 <webdir> [--apply] [--prune] [--url https://SITE]"
[ -d "$WEBDIR" ] || die "not a directory: $WEBDIR"
WEBDIR="$(cd "$WEBDIR" && pwd)"

[ "$WEBDIR" = "$ROOT" ] && die "target is the repo itself — deploy somewhere else"
[ -d "$WEBDIR/.git" ] && die "$WEBDIR is a git clone; this script expects an export target"

command -v git >/dev/null || die "git not found"

# --- refuse to deploy anything that is not committed -------------------------
# Deploying a dirty tree puts files on the web server that are in no commit,
# which is how untracked leftovers reach production. HEAD is the only thing
# this script will ship.
cd "$ROOT" || die "cannot enter $ROOT"
git rev-parse --git-dir >/dev/null 2>&1 || die "$ROOT is not a git repository"

dirty="$(git status --porcelain)"
if [ -n "$dirty" ]; then
    echo "Working tree is not clean:" >&2
    printf '%s\n' "$dirty" >&2
    die "commit or stash first — only HEAD is deployable"
fi

head_sha="$(git rev-parse --short HEAD)"
head_sub="$(git log -1 --format=%s)"

echo "Repo    : $ROOT"
echo "Commit  : $head_sha  $head_sub"
echo "Target  : $WEBDIR"
[ "$APPLY" -eq 1 ] && echo "Mode    : APPLY" || echo "Mode    : dry run (no changes; add --apply to write)"
echo

# --- stage the export --------------------------------------------------------
# Extracted to a temp directory first so the checks below run against exactly
# the bytes that would land, and so a failed check costs nothing.
STAGE="$(mktemp -d)" || die "cannot create temp directory"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

git archive HEAD -- "${SITE_PATHS[@]}" | tar -x -C "$STAGE" \
    || die "git archive failed — check that every SITE_PATHS entry exists at HEAD"

staged_count="$(find "$STAGE" -type f | wc -l)"
[ "$staged_count" -gt 0 ] || die "export is empty — SITE_PATHS may be wrong"

# --- pre-flight checks on the staged tree ------------------------------------
echo "Checks on the export:"
problems=0

# 1. Nothing that should never be served.
bad_count=0
while IFS= read -r f; do
    [ -z "$f" ] && continue
    if [ "$bad_count" -eq 0 ]; then
        echo "  FAIL  files that must not be served are in the export:"
    fi
    echo "        ${f#$STAGE/}"
    bad_count=$((bad_count + 1))
done < <(find "$STAGE" \( -name '*.md' -o -name '*.sql' -o -name '*.yml' -o -name '*.yaml' \
    -o -name 'Dockerfile' -o -name '*~' -o -name '*.bak' -o -name '*.orig' \
    -o -name '*.swp' -o -name '*.phps' \) -print 2>/dev/null)
if [ "$bad_count" -gt 0 ]; then
    problems=$((problems + 1))
else
    echo "  ok    no docs, dumps, compose files, or editor leftovers"
fi

# 2. No dot-directories rode along.
dot_count=0
while IFS= read -r f; do
    [ -z "$f" ] && continue
    if [ "$dot_count" -eq 0 ]; then
        echo "  FAIL  hidden paths in the export:"
    fi
    echo "        ${f#$STAGE/}"
    dot_count=$((dot_count + 1))
done < <(find "$STAGE" -mindepth 1 -name '.*' -not -name '.htaccess' -print 2>/dev/null)
if [ "$dot_count" -gt 0 ]; then
    problems=$((problems + 1))
else
    echo "  ok    no hidden paths besides .htaccess"
fi

# 3. Every directory has an index file. Assume the host may have listings on.
index_fail=0
while IFS= read -r d; do
    # `ls a b` exits 2 when EITHER operand is missing, even though it lists
    # the one that exists — so test the two files separately.
    if [ ! -f "$d/index.html" ] && [ ! -f "$d/index.php" ]; then
        echo "  FAIL  ${d#$STAGE/} has no index file — directory would be browsable"
        index_fail=1
        problems=$((problems + 1))
    fi
done < <(find "$STAGE" -type d)
[ "$index_fail" -eq 0 ] && echo "  ok    every directory has an index file"

# 4. No credentials in the export. The real file is not in the repo, so this
#    should be impossible — check anyway, because it is the one that matters.
if find "$STAGE" -name '*.local.php' -print -quit | grep -q .; then
    echo "  FAIL  a *.local.php file is in the export — credentials must never ship"
    problems=$((problems + 1))
else
    echo "  ok    no credentials file in the export"
fi

# 5. Syntax. A parse error deployed is every page down.
if command -v php >/dev/null; then
    php_bad=0
    while IFS= read -r f; do
        php -l "$f" >/dev/null 2>&1 || { echo "  FAIL  parse error: ${f#$STAGE/}"; php_bad=1; }
    done < <(find "$STAGE" -name '*.php')
    [ "$php_bad" -eq 0 ] && echo "  ok    all PHP parses" || problems=$((problems + 1))
else
    echo "  --    php not on PATH; skipped syntax check"
fi

if [ "$problems" -gt 0 ]; then
    echo
    die "$problems problem(s) above — nothing was written"
fi

# --- what would change -------------------------------------------------------
echo
echo "Export: $staged_count file(s)."

# Files in the web directory that this export does not contain. Usually the
# credentials file (expected) and anything removed from the repo since the
# last deploy (stale).
echo
echo "Already in $WEBDIR but not in this export:"
orphans=()
while IFS= read -r rel; do
    [ -z "$rel" ] && continue
    [ -e "$STAGE/$rel" ] && continue
    case "$rel" in
        includes/*.local.php) echo "  keep    $rel  (credentials — never touched)" ;;
        *)                    echo "  STALE   $rel"; orphans+=("$rel") ;;
    esac
done < <(list_rel_files "$WEBDIR")
[ "${#orphans[@]}" -eq 0 ] && echo "  none"

# --- write -------------------------------------------------------------------
if [ "$APPLY" -eq 0 ]; then
    echo
    echo "Dry run complete. Re-run with --apply to write."
    exit 0
fi

echo
echo "Writing to $WEBDIR ..."
(cd "$STAGE" && tar -c .) | tar -x -C "$WEBDIR" || die "extract into $WEBDIR failed"
find "$WEBDIR" -type d -exec chmod 755 {} +
find "$WEBDIR" -type f -not -name '*.local.php' -exec chmod 644 {} +
# Deployed shell helper must stay executable.
[ -f "$WEBDIR/tools/init_site.sh" ] && chmod 755 "$WEBDIR/tools/init_site.sh"
echo "Wrote $staged_count file(s)."

if [ "${#orphans[@]}" -gt 0 ]; then
    if [ "$PRUNE" -eq 1 ]; then
        for rel in "${orphans[@]}"; do
            rm -f "$WEBDIR/$rel" && echo "  removed  $rel"
        done
    else
        echo
        echo "  ${#orphans[@]} stale file(s) left in place. Review the list above,"
        echo "  then re-run with --prune to remove them."
    fi
fi

# --- post-deploy verification ------------------------------------------------
# init_site.sh ends by printing a check it cannot run because it has no URL.
# With --url, this runs it.
if [ -n "$URL" ]; then
    echo
    echo "Live checks against ${URL%/}:"
    if ! command -v curl >/dev/null; then
        echo "  --    curl not found; skipped"
    else
        code() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$1" 2>/dev/null; }

        # Probes that .htaccess is written to forbid (see .htaccess). A 200 on
        # any of these means overrides are inert or the deny rules are missing.
        for probe in .git/config includes/schema.php includes/bootstrap.php tools/init_site.sh; do
            c="$(code "${URL%/}/$probe")"
            case "$c" in
                403|404) echo "  ok    /$probe -> $c" ;;
                200)     echo "  FAIL  /$probe -> 200 — .htaccess is NOT in effect (or deny rule missing)"; problems=$((problems + 1)) ;;
                *)       echo "  ?     /$probe -> ${c:-no response}" ;;
            esac
        done

        c="$(code "${URL%/}/index.php")"
        [ "$c" = "200" ] && echo "  ok    /index.php -> 200" \
                         || { echo "  FAIL  /index.php -> ${c:-no response} — site is down"; problems=$((problems + 1)); }

        if curl -sI --max-time 10 "${URL%/}/index.php" 2>/dev/null | grep -qi x-content-type-options; then
            echo "  ok    X-Content-Type-Options present"
        else
            echo "  warn  X-Content-Type-Options absent — mod_headers off, or .htaccess inert"
        fi
    fi
fi

# --- next step ---------------------------------------------------------------
echo
echo "Deploy overwrote bootstrap.php. Repoint it at the credentials file:"
echo "    cd $WEBDIR && ./tools/init_site.sh --relink"
echo

[ "${problems:-0}" -gt 0 ] && exit 1
exit 0
