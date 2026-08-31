<?php

namespace App\Services;

use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class MapLinkService
{
    public function resolve(string $url): ?array
    {
        $url = trim($url);
        if (!$this->isAllowedUrl($url)) {
            return null;
        }

        if ($coordinates = $this->extractCoordinates($url)) {
            return [...$coordinates, 'resolved_url' => $url, 'resolved' => false];
        }

        $effectiveUrl = $url;
        $response = null;

        try {
            $response = Http::timeout(10)
                ->connectTimeout(4)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 8,
                        'strict' => true,
                        'referer' => true,
                        'on_redirect' => function ($request, $response, $uri): void {
                            if (!$this->isAllowedUrl((string) $uri)) {
                                throw new \RuntimeException('Google Maps redirect keluar dari host yang diizinkan.');
                            }
                        },
                    ],
                    'on_stats' => function (TransferStats $stats) use (&$effectiveUrl): void {
                        $effectiveUrl = (string) $stats->getEffectiveUri();
                    },
                ])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (!$this->isAllowedUrl($effectiveUrl)) {
            return null;
        }

        // Link bagikan browser biasanya berakhir pada URL yang sudah membawa
        // @lat,lng atau !3d/!4d sehingga bisa dibaca langsung.
        if ($coordinates = $this->extractCoordinates($effectiveUrl)) {
            return [...$coordinates, 'resolved_url' => $effectiveUrl, 'resolved' => $effectiveUrl !== $url];
        }

        // Link dari aplikasi Google Maps kadang berakhir pada /place/.../data=
        // tanpa koordinat pada address bar. Google tetap menaruh URL preview atau
        // meta place:location di HTML respons; baca body sebelum menyerah.
        if ($coordinates = $this->coordinatesFromResponse($response)) {
            return [...$coordinates, 'resolved_url' => $effectiveUrl, 'resolved' => true];
        }

        return null;
    }

    public function extractCoordinates(string $url): ?array
    {
        $decoded = urldecode(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $decoded = str_replace(['\\u003d', '\\u0026', '\\/'], ['=', '&', '/'], $decoded);

        $patterns = [
            '/@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)(?:,|\/|$)/',
            '/!3d(-?\d{1,2}(?:\.\d+)?).*?!4d(-?\d{1,3}(?:\.\d+)?)/s',
            '/[?&](?:q|query|ll|center|destination)=(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $matches)) {
                if ($coordinates = $this->validatedCoordinates($matches[1], $matches[2])) {
                    return $coordinates;
                }
            }
        }

        return null;
    }

    public function canonicalUrl(float|int|string $latitude, float|int|string $longitude): ?string
    {
        $coordinates = $this->validatedCoordinates($latitude, $longitude);
        if (!$coordinates) {
            return null;
        }

        $lat = number_format($coordinates['latitude'], 7, '.', '');
        $lng = number_format($coordinates['longitude'], 7, '.', '');

        return "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}";
    }

    public function isAllowedUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        return $host === 'maps.app.goo.gl'
            || $host === 'goo.gl'
            || $host === 'maps.google.com'
            || $host === 'maps.google.co.id'
            || $host === 'google.com'
            || $host === 'www.google.com'
            || str_ends_with($host, '.google.com')
            || str_ends_with($host, '.google.co.id');
    }

    private function coordinatesFromResponse(?Response $response): ?array
    {
        if (!$response) {
            return null;
        }

        try {
            $body = (string) $response->body();
        } catch (Throwable) {
            return null;
        }

        if ($body === '') {
            return null;
        }

        return $this->coordinatesFromHtml($body);
    }

    private function coordinatesFromHtml(string $html): ?array
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(['\\u003d', '\\u0026', '\\/'], ['=', '&', '/'], $decoded);

        // Fallback paling penting untuk link Share Android/iOS.
        if (
            preg_match(
                '~https?://(?:www\.)?google(?:\.co\.id|\.com)/maps/preview/place/[^"\'<>\s]*?@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)~i',
                $decoded,
                $match
            )
        ) {
            return $this->validatedCoordinates($match[1], $match[2]);
        }

        $latitude = $this->metaContent($decoded, 'place:location:latitude');
        $longitude = $this->metaContent($decoded, 'place:location:longitude');
        if ($latitude !== null && $longitude !== null) {
            if ($coordinates = $this->validatedCoordinates($latitude, $longitude)) {
                return $coordinates;
            }
        }

        // URL Maps berkordinat juga sering tertanam di script/JSON response.
        return $this->extractCoordinates($decoded);
    }

    private function metaContent(string $html, string $property): ?string
    {
        $quotedProperty = preg_quote($property, '~');
        $patterns = [
            '~<meta[^>]+property=["\']' . $quotedProperty . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>~i',
            '~<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']' . $quotedProperty . '["\'][^>]*>~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function validatedCoordinates(string|float|int $latitude, string|float|int $longitude): ?array
    {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['latitude' => $lat, 'longitude' => $lng];
    }
}
