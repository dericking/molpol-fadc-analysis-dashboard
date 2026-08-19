#!/bin/bash
# =============================================================================
# init_site.sh — one-time site setup, and post-deploy repair
# =============================================================================
#
# Creates includes/dbconnect-<random>.local.php from the tracked template,
# writes the real database credentials into it, and repoints bootstrap.php at
# it. Run it again after any deploy: it finds the existing copy and only
# repairs the require line.
#
#   ./tools/init_site.sh            # set up, or repair after a deploy
#   ./tools/init_site.sh --relink   # repair only, never prompt
#
# Why a random name: the copy is the one file holding a real password. Nothing
# in the repo names it, so it survives deploys untouched and cannot be guessed
# from a wordlist. It is written by this script rather than by an editor, so no
# backup or swap file is ever created beside it — those are served as plain
# text and would hand over the credentials.
# =============================================================================

set -u

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
INC="$ROOT/includes"
BOOT="$INC/bootstrap.php"
TEMPLATE="$INC/dbconnect-template.php"
RELINK_ONLY=0
[ "${1:-}" = "--relink" ] && RELINK_ONLY=1

die() { echo "error: $*" >&2; exit 1; }

[ -f "$TEMPLATE" ] || die "template not found: $TEMPLATE"
[ -f "$BOOT" ]     || die "bootstrap not found: $BOOT"

# --- find an existing credentials copy ---------------------------------------
shopt -s nullglob
existing=("$INC"/*.local.php)
shopt -u nullglob

if [ "${#existing[@]}" -gt 1 ]; then
    echo "Found more than one credentials file:" >&2
    printf '  %s\n' "${existing[@]}" >&2
    die "remove the stale one first — only one may exist"
fi

# --- create the copy, or reuse the one that is already there -----------------
if [ "${#existing[@]}" -eq 1 ]; then
    COPY="${existing[0]}"
    echo "Using existing credentials file: $(basename "$COPY")"
elif [ "$RELINK_ONLY" -eq 1 ]; then
    die "--relink given but no includes/*.local.php exists; run without it"
else
    rand="$(head -c 8 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    [ -n "$rand" ] || die "could not generate a random name"
    COPY="$INC/dbconnect-$rand.local.php"

    echo "Creating $(basename "$COPY")"
    echo "Enter the database connection values (input is not echoed for the password)."
    read -r  -p "  DB host            : " db_host
    read -r  -p "  DB port [3306]     : " db_port
    read -r  -p "  DB name            : " db_name
    read -r  -p "  DB user (readonly) : " db_user
    read -rs -p "  DB password        : " db_pass; echo
    db_port="${db_port:-3306}"

    [ -n "$db_host" ] && [ -n "$db_name" ] && [ -n "$db_user" ] \
        || die "host, name and user are required"

    # Single quotes and backslashes would otherwise break the PHP string.
    esc() { printf '%s' "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"; }

    blk="$(mktemp)"
    {
        echo "\$siteDb = ["
        echo "    'host' => '$(esc "$db_host")',"
        echo "    'port' => '$(esc "$db_port")',"
        echo "    'name' => '$(esc "$db_name")',"
        echo "    'user' => '$(esc "$db_user")',"
        echo "    'pass' => '$(esc "$db_pass")',"
        echo "];"
    } > "$blk"

    # Splice the generated block between the markers in the template.
    awk -v blkfile="$blk" '
        /=== BEGIN DEPLOY VALUES/ {
            print
            while ((getline line < blkfile) > 0) print line
            skip = 1
            next
        }
        /=== END DEPLOY VALUES/ { skip = 0; print; next }
        skip { next }
        { print }
    ' "$TEMPLATE" > "$COPY" || die "could not write $COPY"

    rm -f "$blk"

    # 644, not 600. Apache runs as its own user (httpd / www-data), not as the
    # person running this script, so a 600 file owned by you is unreadable to
    # the web server and every page returns 500. The password is therefore
    # readable by other accounts on this machine — which is already true of the
    # whole tree in a shared group area, and is not the boundary that matters:
    # the file is never served over HTTP because it ends in .php.
    chmod 644 "$COPY"
    unset db_pass
fi

# --- point bootstrap.php at the copy -----------------------------------------
base="$(basename "$COPY")"
sed -i "s|^require_once __DIR__ . '/dbconnect[^']*';|require_once __DIR__ . '/$base';|" "$BOOT" \
    || die "could not update $BOOT"

grep -q "require_once __DIR__ . '/$base';" "$BOOT" \
    || die "bootstrap.php was not updated — check its require line by hand"

# --- verify -------------------------------------------------------------------
if command -v php >/dev/null 2>&1; then
    php -l "$COPY" >/dev/null || die "generated file is not valid PHP"
    php -l "$BOOT" >/dev/null || die "bootstrap.php is not valid PHP"
    echo "Syntax OK."
fi

echo
echo "Done. bootstrap.php now requires $base"

# --- deployment hygiene audit -------------------------------------------------
# If the host ignores .htaccess (AllowOverride None) or has directory listings
# enabled, anything present here that is not site content is reachable. These
# warnings are expected in a development checkout; they matter when this runs
# on the web server.
echo
echo "Deployment hygiene:"
warnings=0

check_absent() {
    local path="$1" why="$2"
    if [ -e "$ROOT/$path" ]; then
        echo "  WARNING  $path — $why"
        warnings=$((warnings + 1))
    fi
}

check_absent ".git"       "publishes the entire repo AND its history over HTTP"
check_absent ".cursor"    "agent rules and notes; not site content"
check_absent "docker"     "test-only stack, includes the schema SQL"
check_absent ".A_BACKUP"  "carries a second copy of the schema SQL"

shopt -s nullglob
mds=("$ROOT"/*.md)
shopt -u nullglob
if [ "${#mds[@]}" -gt 0 ]; then
    echo "  WARNING  ${#mds[@]} *.md file(s) in the site root — readable as plain text"
    warnings=$((warnings + 1))
fi

leftovers="$(find "$ROOT" \( -name '*~' -o -name '*.bak' -o -name '*.orig' \
    -o -name '*.save' -o -name '*.swp' -o -name '*.phps' \) -print 2>/dev/null)"
if [ -n "$leftovers" ]; then
    echo "  WARNING  editor leftovers found — these are served as PLAIN TEXT:"
    printf '           %s\n' $leftovers
    warnings=$((warnings + 1))
fi

for d in "$ROOT" "$INC" "$INC/layouts" "$ROOT/assets"; do
    [ -d "$d" ] || continue
    if ! ls "$d"/index.html "$d"/index.php >/dev/null 2>&1; then
        echo "  WARNING  ${d#$ROOT/} has no index file — directory is browsable"
        warnings=$((warnings + 1))
    fi
done

if [ "$warnings" -eq 0 ]; then
    echo "  Clean — nothing unexpected in the tree."
else
    echo
    echo "  $warnings item(s) above. Deploy with:"
    echo "      git archive HEAD | tar -x -C <webdir>"
    echo "  which ships only tracked files — no .git, no ignored files, no leftovers."
fi

echo
echo "One check this script cannot run (needs the site URL):"
echo "  curl -o /dev/null -w '%{http_code}\\n' https://SITE/.git/config"
echo "      -> want 403 or 404. A 200 means the whole repo is downloadable."
