<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV-export die Excel (NL) direct goed opent: puntkomma, UTF-8 met BOM, komma als decimaalteken. */
final class Csv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        Audit::log('export', null, $filename);

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => self::cell($v), $row), ';', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function cell(mixed $v): string
    {
        if (is_float($v) || (is_string($v) && preg_match('/^-?\d+\.\d+$/', $v))) {
            return number_format((float) $v, 2, ',', '');
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d-m-Y');
        }
        $s = (string) $v;

        // Formule-injectie in spreadsheets voorkomen.
        return preg_match('/^[=+\-@\t\r]/', $s) && ! is_numeric($s) ? "'".$s : $s;
    }
}
