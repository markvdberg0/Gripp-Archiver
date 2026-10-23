# Installatie

Werkbank draait op elke server met Docker, of op een gewone PHP-server. De standaarddatabase is SQLite: één bestand, geen aparte databaseserver nodig. Voor meer gebruikers kun je MariaDB/MySQL of PostgreSQL gebruiken.

## Benodigd

- **Docker-route:** Docker Engine 24+ met Docker Compose.
- **Handmatig:** PHP 8.4 (minimaal 8.4.1) of 8.5 met de extensies `pdo_sqlite` (of `pdo_mysql`/`pdo_pgsql`), `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `openssl`, `bcmath`; Composer 2; een webserver (nginx, Apache of Caddy) met de documentroot op `public/`.
- Ongeveer 1 GB schijfruimte plus de omvang van je bijlagen (bij de import worden bijlagen ontdubbeld opgeslagen).

## Met Docker

```bash
cd werkbank
cp .env.example .env
```

Vul in `.env` minimaal in:

- `APP_URL`, bijvoorbeeld `https://werkbank.mijnbedrijf.nl`;
- `APP_KEY`: genereer met `docker compose run --rm --no-deps web php artisan key:generate --show` en plak de uitkomst (`base64:…`).

Start:

```bash
docker compose up -d            # web (poort 8080), queue-worker en scheduler
docker compose exec web php artisan werkbank:gebruiker jij@example.test
```

Het laatste commando maakt de eerste beheerder en toont een tijdelijk wachtwoord. Bij de eerste keer inloggen kies je een eigen wachtwoord.

De containers:

| Service | Doet |
|---|---|
| `web` | De website (Apache + PHP). Voert bij het starten de databasemigraties uit. |
| `queue` | Voert achtergrondtaken uit, zoals een import vanuit het scherm. |
| `scheduler` | Geplande taken, zoals de dagelijkse back-up van Werkbank. |
| `db` | Alleen met `--profile mariadb`: een MariaDB-database. |

Gegevens staan in Docker-volumes: `database` (SQLite-bestand) en `storage` (bestanden). Back-ups van Werkbank komen in `./backups` (instelbaar met `WERKBANK_BACKUP_DIR`).

### Https

Zet Werkbank achter een reverse proxy die https regelt (bijvoorbeeld Caddy, Traefik of nginx). Zet daarna in `.env`:

```
APP_URL=https://werkbank.mijnbedrijf.nl
SESSION_SECURE_COOKIE=true
```

Werkbank vertrouwt `X-Forwarded-*`-headers van private netwerken (instelbaar met `TRUSTED_PROXIES`).

### MariaDB in plaats van SQLite

Zet in `.env`:

```
DB_CONNECTION=mariadb
DB_HOST=db
DB_PORT=3306
DB_DATABASE=werkbank
DB_USERNAME=werkbank
DB_PASSWORD=<een sterk wachtwoord>
```

en start met `docker compose --profile mariadb up -d`.

### De Gripp Back-up koppelen voor import

Zet in `.env` (of in je shell) `GRIPP_BACKUP_DIR=/pad/naar/Gripp-backup/mijnbedrijf`. Docker Compose koppelt die map **alleen-lezen** aan `/import` in de container. Zie [import.md](import.md).

## Handmatig (zonder Docker)

```bash
cd werkbank
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # alleen bij SQLite
php artisan migrate --force
php artisan werkbank:gebruiker jij@example.test
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- Laat de webserver naar `werkbank/public` wijzen. De map `storage/` en `database/` moeten schrijfbaar zijn voor de webserver-gebruiker.
- Start een queue-worker als dienst: `php artisan queue:work --tries=1 --timeout=3600` (bijvoorbeeld met systemd of Supervisor).
- Voeg de scheduler toe aan cron: `* * * * * cd /pad/naar/werkbank && php artisan schedule:run >> /dev/null 2>&1`.

Voorbeeld van een systemd-unit voor de queue-worker:

```ini
[Unit]
Description=Werkbank queue-worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/werkbank
ExecStart=/usr/bin/php artisan queue:work --tries=1 --timeout=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

## Controleren

- `https://<jouw-adres>/up` geeft "OK" als de applicatie draait.
- Log in met de beheerder en open **Beheer › Import** om de koppeling met de back-upmap te controleren.
