<?php

namespace App\Models;

use App\Models\Concerns\FromGripp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Een opgeslagen bestand (tabel files). Inhoud staat op de privéschijf, ontdubbeld op SHA-256. */
class StoredFile extends Model
{
    use FromGripp;

    protected $table = 'files';

    protected $guarded = ['id'];

    public function hasContent(): bool
    {
        return $this->path !== null && Storage::disk('local')->exists($this->path);
    }

    public function sizeLabel(): string
    {
        $b = (int) $this->size;

        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.').' MB' : max(1, (int) round($b / 1024)).' kB';
    }

    /** Pad voor inhoud met deze hash: bestanden/ab/abcdef… (zonder extensie; naam staat in de database). */
    public static function pathFor(string $sha256): string
    {
        return 'bestanden/'.substr($sha256, 0, 2).'/'.$sha256;
    }

    /** Sla inhoud op (als die er nog niet is) en geef het relatieve pad terug. */
    public static function storeContent(string $sourcePath): array
    {
        $sha = hash_file('sha256', $sourcePath);
        $path = self::pathFor($sha);
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $stream = fopen($sourcePath, 'rb');
            $disk->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return ['sha256' => $sha, 'path' => $path, 'size' => filesize($sourcePath)];
    }
}
