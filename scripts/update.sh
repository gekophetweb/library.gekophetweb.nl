#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Updates the production stack (docker-compose.yml) on the Docker host.
# Safe to run from cron: does nothing when there is nothing new.
#
#   scripts/update.sh            new commits on origin/main → back up the database,
#                                fast-forward, rebuild and recreate the containers
#   scripts/update.sh --force    also rebuild when there are no new commits, to pick up
#                                security updates in the base images (PHP, nginx, Node)
#
# Every run also pulls the registry images (mariadb, cloudflared); Compose only
# recreates a container whose image actually changed. Migrations run on their own when
# `web` starts (AUTORUN_LARAVEL_MIGRATION).
#
# Settings (environment variables, all optional):
#   UPDATE_BRANCH        branch to follow                     (default: main)
#   UPDATE_BACKUP_DIR    where database dumps go              (default: <repo>/backups)
#   UPDATE_KEEP_BACKUPS  number of dumps to keep              (default: 14)
#
# Example crontab (crontab -e, as a user in the `docker` group that owns the checkout):
#   */15 * * * * /opt/library.gekophetweb.nl/scripts/update.sh >> /var/log/library-update.log 2>&1
#   30 4 * * 0   /opt/library.gekophetweb.nl/scripts/update.sh --force >> /var/log/library-update.log 2>&1
# ---------------------------------------------------------------------------
set -Eeuo pipefail

# cron starts with a minimal PATH; make sure git and docker are found.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="${UPDATE_BRANCH:-main}"
BACKUP_DIR="${UPDATE_BACKUP_DIR:-$REPO_DIR/backups}"
KEEP_BACKUPS="${UPDATE_KEEP_BACKUPS:-14}"
LOCK_FILE="${TMPDIR:-/tmp}/library-gekophetweb-update.lock"

FORCE=false
case "${1:-}" in
    "") ;;
    --force) FORCE=true ;;
    -h | --help)
        sed -n '2,24p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
        exit 0
        ;;
    *)
        echo "Unknown option: $1 (use --force or --help)" >&2
        exit 2
        ;;
esac

log() { printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }
fail() {
    log "ERROR: $*"
    exit 1
}
trap 'fail "command failed on line $LINENO: $BASH_COMMAND"' ERR

compose() { docker compose --project-directory "$REPO_DIR" -f "$REPO_DIR/docker-compose.yml" "$@"; }

# Only one run at a time: a slow build must not overlap with the next cron tick.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    log "Another update is still running; skipping."
    exit 0
fi

cd "$REPO_DIR"

# --- 1. Code -----------------------------------------------------------------
current_branch="$(git rev-parse --abbrev-ref HEAD)"
[[ "$current_branch" == "$BRANCH" ]] ||
    fail "checkout is on '$current_branch', expected '$BRANCH'; not touching it."

# Local edits to tracked files would be overwritten or block the merge. Untracked and
# ignored files (.env, backups/) are fine.
[[ -z "$(git status --porcelain --untracked-files=no)" ]] ||
    fail "checkout has local changes to tracked files; commit or discard them first."

git fetch --quiet origin "$BRANCH"
old_rev="$(git rev-parse HEAD)"
new_rev="$(git rev-parse "origin/$BRANCH")"

if [[ "$old_rev" == "$new_rev" ]]; then
    code_changed=false
else
    git merge-base --is-ancestor "$old_rev" "$new_rev" ||
        fail "local $BRANCH has commits that are not on origin/$BRANCH; resolve by hand."
    code_changed=true
fi

# --- 2. Registry images (mariadb, cloudflared) ----------------------------------
log "Pulling registry images."
compose pull --quiet --ignore-buildable

if [[ "$code_changed" == false && "$FORCE" == false ]]; then
    # Recreates db/cloudflared only if the pull above brought a newer image.
    compose up -d --wait --remove-orphans
    log "No new commits on origin/$BRANCH (${old_rev:0:7}); containers are up to date."
    exit 0
fi

# --- 3. Database backup, before migrations can run -------------------------------
if [[ -n "$(compose ps --status running --quiet db)" ]]; then
    mkdir -p "$BACKUP_DIR"
    backup_file="$BACKUP_DIR/library-$(date '+%Y%m%d-%H%M%S')-${old_rev:0:7}.sql.gz"
    log "Backing up the database to $backup_file."
    # The root password stays inside the db container; it never appears on this
    # command line or in the log.
    # shellcheck disable=SC2016  # expanded by the shell inside the container
    compose exec -T db sh -c \
        'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' |
        gzip >"$backup_file.part"
    mv "$backup_file.part" "$backup_file"

    # Keep the newest $KEEP_BACKUPS dumps.
    find "$BACKUP_DIR" -maxdepth 1 -name 'library-*.sql.gz' -type f -printf '%T@ %p\n' |
        sort -rn | tail -n "+$((KEEP_BACKUPS + 1))" | cut -d' ' -f2- |
        while IFS= read -r old_backup; do rm -f -- "$old_backup"; done
else
    log "db container is not running; skipping the backup."
fi

# --- 4. Apply ----------------------------------------------------------------------
if [[ "$code_changed" == true ]]; then
    log "Updating ${old_rev:0:7} → ${new_rev:0:7}:"
    git log --oneline --no-decorate "$old_rev..$new_rev" | sed 's/^/      /'
    git merge --quiet --ff-only "origin/$BRANCH"

    # A new required .env setting makes Compose refuse the file. Go back to the old
    # code so the running containers and the checkout keep matching.
    if ! config_error="$(compose config --quiet 2>&1)"; then
        git reset --quiet --hard "$old_rev"
        fail "docker-compose.yml no longer validates (missing .env setting?); rolled the checkout back to ${old_rev:0:7}. $config_error"
    fi
else
    log "Forced rebuild of ${old_rev:0:7}."
fi

log "Building the app image."
compose build --pull --quiet

log "Recreating containers."
compose up -d --wait --remove-orphans ||
    fail "containers did not become healthy; check 'docker compose ps' and 'docker compose logs web'. Previous version: ${old_rev:0:7}${backup_file:+, backup: $backup_file}."

docker image prune --force >/dev/null
log "Done: now on $(git rev-parse --short HEAD)."
