#!/bin/sh
set -eu

if [ "$#" -ne 2 ]; then
    echo "Uso: $0 RUTA_RESPALDO BASE_DESTINO_test" >&2
    exit 1
fi

pilot_backup_path=$1
pilot_target_database=$2
pilot_env_file=${PILOT_ENV_FILE:-.env.pilot}
case "$pilot_env_file" in
    */*) ;;
    *) pilot_env_file=./$pilot_env_file ;;
esac

if ! printf '%s' "$pilot_target_database" | grep -Eq '^[A-Za-z_][A-Za-z0-9_]*_test$'; then
    echo "La base destino debe ser un identificador seguro y terminar en _test." >&2
    exit 1
fi

if [ ! -f "$pilot_backup_path/postgresql.dump" ] || [ ! -f "$pilot_backup_path/checksums.sha256" ]; then
    echo "El respaldo no contiene dump y checksums verificables." >&2
    exit 1
fi

(cd "$pilot_backup_path" && sha256sum --check checksums.sha256)

set -a
. "$pilot_env_file"
set +a
: "${DB_USERNAME:?DB_USERNAME is required}"
export PILOT_ENV_FILE=$pilot_env_file

pilot_compose="docker compose --env-file $pilot_env_file -f compose.pilot.yaml"
pilot_exists=$(printf '%s' "$pilot_target_database" | sed "s/'/''/g")
# shellcheck disable=SC2086
if $pilot_compose exec -T postgres psql --username "$DB_USERNAME" --dbname postgres --tuples-only --no-align --command "select 1 from pg_database where datname = '$pilot_exists'" | grep -q 1; then
    echo "La base $pilot_target_database ya existe; no se sobrescribe." >&2
    exit 1
fi

# shellcheck disable=SC2086
$pilot_compose exec -T postgres createdb --username "$DB_USERNAME" "$pilot_target_database"
# shellcheck disable=SC2086
$pilot_compose exec -T postgres pg_restore \
    --username "$DB_USERNAME" \
    --dbname "$pilot_target_database" \
    --no-owner \
    --no-privileges < "$pilot_backup_path/postgresql.dump"

echo "Restauración aislada completada en $pilot_target_database."
echo "Los documentos permanecen en $pilot_backup_path/documents para verificación; este script no escribe en R2."
