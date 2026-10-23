# Licenties

Werkbank zelf valt onder de **MIT-licentie** ([LICENSE](../../LICENSE)). Alle vormgeving (CSS, het "W"-icoon), teksten en code zijn eigen werk. Er zijn geen code, afbeeldingen, logo's of teksten van Gripp B.V. gebruikt.

## Afhankelijkheden (productie)

Overzicht gemaakt met `composer licenses --no-dev`. Alle licenties staan opensource-distributie toe.

| Licentie | Pakketten | Toelichting |
|---|---|---|
| MIT | 73, waaronder `laravel/framework`, `symfony/*`, `nesbot/carbon`, `monolog/monolog`, `guzzlehttp/*`, `league/flysystem`, `barryvdh/laravel-dompdf`, `symfony/html-sanitizer` | Vrij te gebruiken, met behoud van de copyrightvermelding. |
| BSD-3-Clause | `league/commonmark`, `league/config`, `nikic/php-parser`, `tijsverkoyen/css-to-inline-styles`, `vlucas/phpdotenv` | Vergelijkbaar met MIT. |
| BSD-3-Clause / GPL-2.0 / GPL-3.0 (naar keuze) | `nette/schema`, `nette/utils` | Gebruikt onder BSD-3-Clause. |
| Apache-2.0 | `phpoption/phpoption` | Vrij te gebruiken; behoud NOTICE/vermelding. |
| LGPL-2.1 / LGPL-2.1-or-later / LGPL-3.0-or-later | `dompdf/dompdf`, `dompdf/php-font-lib`, `dompdf/php-svg-lib` | PDF-generatie. Ongewijzigd als losse bibliotheek gebruikt (via Composer), wat LGPL toestaat; gebruikers kunnen de bibliotheek vervangen of bijwerken. |

Ontwikkelafhankelijkheden (niet in het Docker-image): `phpunit/phpunit` (BSD-3-Clause), `laravel/pint` (MIT), `mockery/mockery` (BSD-3-Clause), `fakerphp/faker` (MIT), `nunomaduro/collision` (MIT).

## Docker

- Basis-image `php:8.4-apache` (PHP License v3.01 en Apache License 2.0, plus Debian-pakketten onder hun eigen licenties).
- `mlocati/php-extension-installer` (MIT), alleen tijdens het bouwen.
- Optioneel `mariadb:11.4` (GPL-2.0, als losse dienst).

## Bijwerken van dit overzicht

```bash
composer licenses --no-dev
```
