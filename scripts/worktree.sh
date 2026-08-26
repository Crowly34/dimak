#!/usr/bin/env bash
#
# Per-worktree Herd site + MySQL databases.
#
#   scripts/worktree.sh slug <branch>           aeo-19-upgrade-to-pest-5 -> aeo-19
#   scripts/worktree.sh up   <branch> <path>    link site, create dbs, write env, migrate
#   scripts/worktree.sh down <branch>           unlink site, drop dbs
#
# Driven by worktrunk's pre-start / post-remove hooks; see ~/.config/worktrunk/config.toml.
# Run `up`/`down` by hand only to repair a worktree that got out of sync.

set -euo pipefail

PROJECT=dimak
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=

# DBngin keeps its mysql client out of PATH under a version-stamped directory;
# glob for the newest so a DBngin upgrade doesn't silently break the hooks.
mysql_client() {
    local found
    found=$(ls -d /Users/Shared/DBngin/mysql/*/bin/mysql 2>/dev/null | sort -V | tail -1)
    if [[ -x ${found:-} ]]; then
        printf '%s' "$found"
    elif command -v mysql >/dev/null; then
        command -v mysql
    else
        echo "worktree.sh: no mysql client found — is DBngin installed?" >&2
        return 1
    fi
}

mysql_do() {
    "$(mysql_client)" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
        ${DB_PASS:+-p"$DB_PASS"} --batch --skip-column-names -e "$1"
}

# Linear names branches <user>/<ticket>-<title>. The ticket id alone is the useful
# handle, so aeo-19-upgrade-the-test-suite-to-pest-5 becomes aeo-19 — short enough
# to type as a URL. Anything without a ticket prefix falls back to the full slug.
slug() {
    local branch=${1:?slug: branch required}
    branch=${branch##*/}
    branch=$(printf '%s' "$branch" | tr '[:upper:]' '[:lower:]')
    if [[ $branch =~ ^([a-z]+-[0-9]+) ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
    else
        printf '%s' "$branch" | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g' | cut -c1-40
    fi
}

site_name() { printf '%s.%s' "$(slug "$1")" "$PROJECT"; }
db_name()   { printf '%s_%s' "$PROJECT" "$(slug "$1" | tr '-' '_')"; }

# The primary worktree's own site and databases are not ours to touch.
assert_not_primary() {
    local db=$1
    if [[ -z ${db#${PROJECT}_} || $db == "$PROJECT" ]]; then
        echo "worktree.sh: refusing to act on the primary '$PROJECT' database" >&2
        exit 1
    fi
}

write_env() {
    local file=$1 app_env=$2 url=$3 db=$4
    sed -i '' \
        -e "s|^APP_ENV=.*|APP_ENV=$app_env|" \
        -e "s|^APP_URL=.*|APP_URL=$url|" \
        -e "s|^DB_DATABASE=.*|DB_DATABASE=$db|" \
        "$file"
}

cmd_up() {
    local branch=${1:?up: branch required} path=${2:?up: worktree path required}
    local site db url
    site=$(site_name "$branch")
    db=$(db_name "$branch")
    url="https://${site}.test"
    assert_not_primary "$db"

    echo "==> ${branch}  ->  ${url}  ->  ${db} / ${db}_test"

    mysql_do "CREATE DATABASE IF NOT EXISTS \`${db}\`;"
    mysql_do "CREATE DATABASE IF NOT EXISTS \`${db}_test\`;"

    cd "$path"

    # herd link rewrites APP_URL, so link and secure before writing the env files.
    herd link "$site"
    herd secure "$site"

    [[ -f .env ]] || cp .env.example .env
    write_env .env local "$url" "$db"

    cp .env .env.testing
    write_env .env.testing testing "$url" "${db}_test"
    sed -i '' -e 's|^GOOGLE_SERVICE_ENABLED=.*|GOOGLE_SERVICE_ENABLED=false|' .env.testing

    php artisan migrate --force
    php artisan migrate --force --env=testing
}

cmd_down() {
    local branch=${1:?down: branch required}
    local site db
    site=$(site_name "$branch")
    db=$(db_name "$branch")
    assert_not_primary "$db"

    echo "==> tearing down ${branch}: ${site}.test + databases"

    herd unsecure "$site" || true
    herd unlink "$site" || true

    # Beyond the two databases we create, `artisan test --parallel` spawns one
    # <db>_test_test_N per process. Match them exactly: LIKE would be wrong twice
    # over, since `_` is a LIKE wildcard and a prefix match on dimak_aeo_19 would
    # also swallow dimak_aeo_190.
    local -a doomed
    while IFS= read -r schema; do
        [[ -n $schema ]] && doomed+=("$schema")
    done < <(mysql_do "SELECT schema_name FROM information_schema.schemata
                       WHERE schema_name = '${db}'
                          OR schema_name = '${db}_test'
                          OR schema_name REGEXP '^${db}_test_test_[0-9]+$';")

    if [[ ${#doomed[@]} -eq 0 ]]; then
        echo "    no databases to drop"
        return
    fi
    for schema in "${doomed[@]}"; do
        echo "    dropping ${schema}"
        mysql_do "DROP DATABASE IF EXISTS \`${schema}\`;"
    done
}

case ${1:-} in
    slug) shift; slug "$@"; echo ;;
    up)   shift; cmd_up "$@" ;;
    down) shift; cmd_down "$@" ;;
    *)    sed -n '3,10p' "$0" >&2; exit 1 ;;
esac
