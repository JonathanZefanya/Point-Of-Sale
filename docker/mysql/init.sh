#!/bin/bash
# File .sql di docker-entrypoint-initdb.d tidak mengganti variabel env,
# jadi init.sql dijalankan lewat skrip ini setelah ${DB_PASSWORD} disubstitusi.
set -euo pipefail

if [ -z "${DB_PASSWORD:-}" ]; then
  echo "DB_PASSWORD wajib diisi di .env root." >&2
  exit 1
fi

escaped=$(printf '%s' "$DB_PASSWORD" | sed -e "s/[\\']/\\&/g" -e 's/[&|]/\&/g')
sed "s|\${DB_PASSWORD}|${escaped}|g" /docker-init/init.sql \
  | mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}"
