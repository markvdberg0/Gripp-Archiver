<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\StoredFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Bestanden (Gripp: file). De inhoud komt uit archive/ van de back-up en wordt op SHA-256 ontdubbeld
 * opgeslagen. Ontbreekt de inhoud in het archief, dan blijft het record bestaan zonder inhoud.
 */
class FileMapper extends Mapper
{
    public function entity(): string
    {
        return 'file';
    }

    public function model(): string
    {
        return StoredFile::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $info = $ctx->snapshot->file($row['id']);
        $attrs = ['name' => Gripp::str($info['name'] ?? null) ?? Gripp::str($row['title'] ?? $row['searchname'] ?? null) ?? 'bestand-'.$row['id']];
        if (! $info) {
            $ctx->files['missing']++;

            return $attrs;
        }
        $ctx->files['copied']++;
        if ($ctx->dryRun) {
            return $attrs;
        }

        return $attrs + StoredFile::storeContent($info['path']) + [
            'mime_type' => MimeTypes::getDefault()->guessMimeType($info['path']) ?? 'application/octet-stream',
        ];
    }

    /** Komt de inhoud later alsnog in het archief, dan wordt het record bijgewerkt. */
    public function hashExtra(array $row, Context $ctx): string
    {
        return (string) ($ctx->snapshot->file($row['id'])['sha256'] ?? 'geen-inhoud');
    }
}
