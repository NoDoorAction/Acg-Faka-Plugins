<?php
declare(strict_types=1);

namespace App\Plugin\ShineBanner\Library;

class Url
{
    /**
     * Allow only http(s) URLs or local relative paths. Protocol-relative URLs
     * are intentionally rejected so they cannot bypass the protocol allowlist.
     */
    public static function sanitize(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme)) {
            return in_array(strtolower($scheme), ['http', 'https'], true)
                && filter_var($url, FILTER_VALIDATE_URL) !== false
                ? $url
                : '';
        }

        // A URL without a scheme is only safe as a local path when it cannot
        // be protocol-relative, contain a backslash, or contain whitespace.
        return !str_starts_with($url, '//')
            && !str_contains($url, '\\')
            && !preg_match('/\s/', $url)
            ? $url
            : '';
    }
}
