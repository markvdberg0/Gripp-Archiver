<?php

return [
    /*
    | Back-upmap van de Gripp Back-up-tool waaruit geïmporteerd wordt (de map met data/ en archive/).
    | In Docker: koppel de back-upmap alleen-lezen aan /import (zie docker-compose.yml).
    */
    'import_path' => env('WERKBANK_IMPORT_PATH'),

    /*
    | Waar Werkbank back-ups van zichzelf neerzet (php artisan werkbank:backup).
    */
    'backup_path' => env('WERKBANK_BACKUP_PATH', storage_path('app/backups')),

    'backup_keep' => (int) env('WERKBANK_BACKUP_KEEP', 14),

    /*
    | Maximale grootte van een upload in kilobytes.
    */
    'max_upload_kb' => (int) env('WERKBANK_MAX_UPLOAD_KB', 20480),
];
