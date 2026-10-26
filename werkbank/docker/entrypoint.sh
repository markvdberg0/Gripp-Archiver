#!/bin/sh
# Start van de Werkbank-container. Wordt gebruikt voor web, queue en scheduler.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "FOUT: APP_KEY ontbreekt. Genereer er een met:" >&2
  echo "  docker compose run --rm web php artisan key:generate --show" >&2
  echo "en zet de uitkomst in .env (APP_KEY=base64:...)." >&2
  exit 1
fi

mkdir -p storage/app/private storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
fi
chown -R www-data:www-data storage database bootstrap/cache 2>/dev/null || true

# Alleen de webcontainer migreert (WERKBANK_MIGRATE=true), zodat queue en scheduler niet tegelijk migreren.
if [ "${WERKBANK_MIGRATE:-false}" = "true" ]; then
  su -s /bin/sh www-data -c "php artisan migrate --force --no-interaction"
fi
su -s /bin/sh www-data -c "php artisan config:cache && php artisan route:cache && php artisan view:cache" >/dev/null

if [ "$1" = "apache2-foreground" ]; then
  exec "$@"
fi
# Overige opdrachten (queue:work, schedule:work, artisan …) als www-data.
exec su -s /bin/sh www-data -c "$*"
