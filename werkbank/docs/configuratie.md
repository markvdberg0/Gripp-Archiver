# Configuratie

Alle instellingen staan in `.env`. [`.env.example`](../.env.example) bevat elke instelling met uitleg en **geen geheimen**. Zet echte wachtwoorden en sleutels alleen in `.env`; dat bestand staat nooit in git.

## Belangrijkste instellingen

| Instelling | Betekenis |
|---|---|
| `APP_NAME` | Naam in de interface (standaard Werkbank). |
| `APP_URL` | Het adres waarop Werkbank bereikbaar is. |
| `APP_KEY` | Versleutelingssleutel voor sessies en cookies. **Bewaar hem veilig** (bijvoorbeeld in je wachtwoordkluis); zonder deze sleutel werken bestaande sessies niet meer. Genereer met `php artisan key:generate`. |
| `APP_DEBUG` | Altijd `false` in productie: anders zijn foutdetails zichtbaar. |
| `APP_TIMEZONE` | Standaard `Europe/Amsterdam`. |
| `DB_CONNECTION` | `sqlite` (standaard), `mariadb`, `mysql` of `pgsql`. |
| `DB_DATABASE` | Bij SQLite het pad naar het bestand; anders de databasenaam. |
| `SESSION_LIFETIME` | Minuten tot automatisch uitloggen bij inactiviteit (standaard 480). |
| `SESSION_SECURE_COOKIE` | `true` zodra Werkbank via https bereikbaar is. |
| `LOG_CHANNEL`, `LOG_LEVEL`, `LOG_DAILY_DAYS` | Technische logbestanden (in `storage/logs`). Het logboek van gebruikersacties staat los daarvan in de database. |
| `MAIL_*` | Uitgaande e-mail voor documenten, herinneringen en wachtwoordherstel. Voor SMTP: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT` (587 met STARTTLS), `MAIL_USERNAME`, `MAIL_PASSWORD` en `MAIL_FROM_ADDRESS` (een adres van je eigen domein, met SPF/DKIM). Met `MAIL_MAILER=log` gaan mails naar `storage/logs` en wordt niets echt verstuurd. Antwoorden gaan naar de gebruiker die verstuurde (reply-to). |
| `QUEUE_CONNECTION` | Standaard `database`. Er moet dan een queue-worker draaien. Met `sync` worden taken direct uitgevoerd (alleen voor kleine installaties of testen). |
| `WERKBANK_IMPORT_PATH` | Map van de Gripp Back-up voor de import (in Docker: `/import`). |
| `WERKBANK_BACKUP_PATH` | Waar `werkbank:backup` de back-ups van Werkbank neerzet. |
| `WERKBANK_BACKUP_KEEP` | Aantal back-ups dat bewaard blijft (standaard 14). |
| `WERKBANK_MAX_UPLOAD_KB` | Maximale grootte van een upload (standaard 20 MB). Let op: ook PHP (`upload_max_filesize`) en de webserver moeten dit toestaan. |
| `TRUSTED_PROXIES` | IP-adressen of reeksen van je reverse proxy. |

Na het wijzigen van `.env` in productie: `php artisan config:cache` (Docker: `docker compose restart`).

## Bedrijfsgegevens

Bedrijfsnaam, adres, KvK, btw-nummer, IBAN en de nummering van offertes en facturen stel je in via **Beheer › Instellingen** in de applicatie (vanaf fase 2). Die staan in de database en gaan mee in de back-up.
