# Updates

Werkbank volgt de gewone Laravel-werkwijze: nieuwe code ophalen, afhankelijkheden bijwerken, databasemigraties uitvoeren. Migraties voegen alleen tabellen en kolommen toe; ze verwijderen geen gegevens.

## Altijd eerst

1. Lees in [CHANGELOG.md](../../CHANGELOG.md) wat er verandert.
2. Maak een back-up: `php artisan werkbank:backup --label=voor-update` (Docker: `docker compose exec web php artisan werkbank:backup --label=voor-update`).

## Met Docker

```bash
git pull                        # of pak de nieuwe release uit
docker compose build
docker compose up -d            # de web-container voert de migraties uit bij het starten
docker compose logs web | tail  # controleer of het starten goed ging
```

## Zonder Docker

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

## Terug naar de vorige versie

Ging er iets mis: zet de vorige code terug (`git checkout <vorige tag>` of de vorige release), en zet de back-up van vóór de update terug met `php artisan werkbank:herstel <bestand>`. Een back-up van een nieuwere versie kan niet in een oudere versie worden teruggezet; het herstel controleert dat.

## PHP en Laravel

Werkbank draait op PHP 8.3 tot en met 8.5 en Laravel 13. Beveiligingsupdates van afhankelijkheden: `composer update` gevolgd door de tests (`php artisan test`). De GitHub-workflow draait de tests op alle ondersteunde PHP-versies en op SQLite, MariaDB en PostgreSQL.
