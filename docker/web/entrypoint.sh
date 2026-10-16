#!/bin/sh
set -e
cd /app

# Sinkronkan node_modules jika package-lock.json berubah setelah image dibangun
if [ ! -f node_modules/.package-lock.json ] || ! cmp -s package-lock.json node_modules/.lock-snapshot 2>/dev/null; then
  npm ci
  cp package-lock.json node_modules/.lock-snapshot
fi

exec "$@"
