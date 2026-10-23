<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Veilig tonen van tekst die HTML kan bevatten (bijvoorbeeld geïmporteerde e-mails): scripts, stijlen,
 * formulieren en externe afbeeldingen worden verwijderd. Gewone tekst wordt ge-escaped.
 */
final class Html
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function render(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }
        if (! preg_match('/<\/?[a-z][\s\S]*>/i', $text)) {
            return new HtmlString(nl2br(e($text)));
        }

        return new HtmlString(self::sanitizer()->sanitize($text));
    }

    public static function plain(?string $text, int $max = 200): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max - 1).'…' : $plain;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->blockElement('img')
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->forceAttribute('a', 'target', '_blank')
                ->dropAttribute('style', '*')
                ->dropAttribute('class', '*')
                ->dropAttribute('id', '*')
                ->withMaxInputLength(500_000)
        );
    }
}
