#!/bin/sh
# Start-up tasks, then hand over to Apache.
set -eu

APP=/var/www/html

# 1. Wait for the database and create / upgrade the schema (idempotent).
tries=0
until php "$APP/database/install.php"; do
  tries=$((tries + 1))
  if [ "$tries" -ge 20 ]; then
    echo "Database still unreachable after $tries attempts; starting the web server anyway." >&2
    break
  fi
  echo "Waiting for the database (attempt $tries)..." >&2
  sleep 3
done

# 2. Optional demo data (students with KNOWN passwords: never enable on a public site you care about).
if [ "${SEED_DEMO:-0}" = "1" ]; then
  php "$APP/database/seed.php" --allow-production || true
fi

# 3. Make sure the administrator account from the environment exists.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
  php "$APP/database/create_admin.php" || true
else
  echo "ADMIN_EMAIL / ADMIN_PASSWORD not set: no admin account will be created automatically." >&2
fi

exec "$@"
