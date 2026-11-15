#!/bin/sh
set -eu

PORT="${PORT:-10000}"

# Render routes external traffic to $PORT (default 10000).
sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:[0-9]+>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Keep uploaded IDs/receipts/avatars outside DocumentRoot and ensure writable paths exist.
PRIVATE_DIR="${PRIVATE_STORAGE_DIR:-/var/www/SantaBeachClub-private}"
mkdir -p \
    "${PRIVATE_DIR}/receipts" \
    "${PRIVATE_DIR}/avatars" \
    "${PRIVATE_DIR}/discount_ids" \
    /var/www/html/backend/logs \
    /var/www/html/frontend/uploads
chown -R www-data:www-data "${PRIVATE_DIR}" /var/www/html/backend/logs /var/www/html/frontend/uploads

# Render Free Web Services do not provide interactive shell access, so run
# idempotent migrations and optional env-based admin bootstrap on startup.
if [ -n "${DB_HOST:-}" ]; then
    php /var/www/html/backend/scripts/migrate.php || echo "WARNING: Database migration failed or timed out on startup." >&2
    if [ -n "${INITIAL_ADMIN_EMAIL:-}" ] && [ -n "${INITIAL_ADMIN_PASSWORD:-}" ]; then
        php /var/www/html/backend/scripts/create_initial_admin.php --from-env || echo "WARNING: Initial admin bootstrap skipped or failed." >&2
    fi
fi

exec apache2-foreground
