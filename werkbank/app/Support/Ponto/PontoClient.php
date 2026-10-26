<?php

namespace App\Support\Ponto;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimale client voor de Ponto API met een custom integration (OAuth2 client credentials), alleen lezen.
 * Start bewust geen synchronisaties: die mogen volgens de voorwaarden van Ponto alleen met de gebruiker erbij.
 */
class PontoClient
{
    public static function configured(): bool
    {
        return filled(config('services.ponto.client_id')) && filled(config('services.ponto.client_secret'));
    }

    /** @return list<array> rekeningen (JSON:API-resources) */
    public function accounts(): array
    {
        return iterator_to_array($this->paginate('/accounts'), false);
    }

    /**
     * Transacties van een rekening, pagina voor pagina (100 per pagina).
     *
     * @return \Generator<int, list<array>>
     */
    public function transactionPages(string $accountId): \Generator
    {
        $url = $this->url("/accounts/{$accountId}/transactions?page[limit]=100");
        for ($page = 0; $url && $page < max(1, (int) config('services.ponto.max_pages', 50)); $page++) {
            $json = $this->get($url);
            yield $json['data'] ?? [];
            $url = self::next($json, $url);
        }
    }

    private function paginate(string $path): \Generator
    {
        $url = $this->url($path.'?page[limit]=100');
        for ($page = 0; $url && $page < 100; $page++) {
            $json = $this->get($url);
            yield from $json['data'] ?? [];
            $url = self::next($json, $url);
        }
    }

    /** Volgende pagina, of null; ook null als Ponto dezelfde pagina opnieuw aanwijst (geen eindeloze lus). */
    private static function next(array $json, string $current): ?string
    {
        $next = $json['links']['next'] ?? null;

        return $next && $next !== $current && ($json['data'] ?? []) !== [] ? $next : null;
    }

    private function get(string $url): array
    {
        try {
            return $this->http()->withToken($this->token())->accept('application/vnd.api+json')->get($url)->throw()->json() ?? [];
        } catch (RequestException $e) {
            throw new RuntimeException('Ponto gaf een fout ('.$e->response->status().'): '.$this->errorText($e), 0, $e);
        }
    }

    /** Toegangstoken (30 minuten geldig bij Ponto), gecachet tot kort voor het verloopt. */
    private function token(): string
    {
        return Cache::remember('ponto.token.'.sha1((string) config('services.ponto.client_id')), now()->addMinutes(25), function () {
            try {
                $json = $this->http()->asForm()->accept('application/vnd.api+json')
                    ->withBasicAuth((string) config('services.ponto.client_id'), (string) config('services.ponto.client_secret'))
                    ->post($this->url('/oauth2/token'), ['grant_type' => 'client_credentials'])->throw()->json();
            } catch (RequestException $e) {
                throw new RuntimeException('Aanmelden bij Ponto mislukt ('.$e->response->status().'): '.$this->errorText($e).' Controleer PONTO_CLIENT_ID en PONTO_CLIENT_SECRET.', 0, $e);
            }

            return $json['access_token'] ?? throw new RuntimeException('Ponto gaf geen toegangstoken terug.');
        });
    }

    private function http(): PendingRequest
    {
        $options = [];
        if ($cert = config('services.ponto.cert')) {
            $pass = config('services.ponto.passphrase');
            $options['cert'] = $pass ? [$cert, $pass] : $cert;
            if ($key = config('services.ponto.key')) {
                $options['ssl_key'] = $pass ? [$key, $pass] : $key;
            }
        }

        return Http::withOptions($options)->timeout(30)->retry(2, 500, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.ponto.url'), '/').$path;
    }

    private function errorText(RequestException $e): string
    {
        $errors = $e->response->json('errors') ?? [];

        return collect($errors)->map(fn ($err) => $err['detail'] ?? $err['code'] ?? '')->filter()->implode(' ') ?: 'geen details';
    }
}
