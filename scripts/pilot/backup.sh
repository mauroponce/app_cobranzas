#!/bin/sh
set -eu

pilot_env_file=${PILOT_ENV_FILE:-.env.pilot}
case "$pilot_env_file" in
    */*) ;;
    *) pilot_env_file=./$pilot_env_file ;;
esac

if [ ! -f "$pilot_env_file" ]; then
    echo "No existe $pilot_env_file. Copiá .env.pilot.example y completalo fuera de Git." >&2
    exit 1
fi

set -a
. "$pilot_env_file"
set +a

pilot_backup_root=${PILOT_BACKUP_ROOT:-./var/pilot-backups}
export PILOT_ENV_FILE=$pilot_env_file
export PILOT_BACKUP_ROOT=$pilot_backup_root

: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"

case "$pilot_backup_root" in
    /|"") echo "PILOT_BACKUP_ROOT no puede ser vacío ni /." >&2; exit 1 ;;
esac

pilot_timestamp=$(date -u +%Y%m%dT%H%M%SZ)
pilot_destination="$pilot_backup_root/$pilot_timestamp"
mkdir -p "$pilot_backup_root"
if ! mkdir "$pilot_destination"; then
    echo "Ya existe el destino $pilot_destination; no se sobrescribe." >&2
    exit 1
fi
chmod 700 "$pilot_destination"

pilot_compose="docker compose --env-file $pilot_env_file -f compose.pilot.yaml"
pilot_dump_partial="$pilot_destination/postgresql.dump.partial"
pilot_dump="$pilot_destination/postgresql.dump"

# shellcheck disable=SC2086
$pilot_compose exec -T postgres pg_dump \
    --username "$DB_USERNAME" \
    --dbname "$DB_DATABASE" \
    --format=custom \
    --no-owner \
    --no-privileges > "$pilot_dump_partial"
mv "$pilot_dump_partial" "$pilot_dump"
chmod 600 "$pilot_dump"

pilot_host_user="$(id -u):$(id -g)"
# Ejecutar con el UID/GID del operador evita dejar respaldos montados como root.
# shellcheck disable=SC2086
$pilot_compose exec -T --user "$pilot_host_user" app php artisan pilot:backup-documents "/backups/$pilot_timestamp/documents"

git rev-parse HEAD > "$pilot_destination/git-revision.txt"
awk -F= '/^[A-Za-z_][A-Za-z0-9_]*=/{print $1}' "$pilot_env_file" | sort > "$pilot_destination/environment-keys.txt"
sha256sum compose.pilot.yaml infra/docker/php/Dockerfile infra/docker/nginx/Dockerfile.pilot > "$pilot_destination/configuration.sha256"
(cd "$pilot_destination" && find . -type f ! -name checksums.sha256 -exec sha256sum {} \; > checksums.sha256)
find "$pilot_destination" -type f -exec chmod 600 {} \;

echo "Respaldo creado en $pilot_destination"
