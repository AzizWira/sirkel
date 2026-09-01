<?php

namespace App\Services;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class RegionService
{
    /**
     * District dropdowns always have a complete local fallback.
     * BinderByte remains useful to refresh the database, but forms do not
     * become unusable merely because a sync has not been run yet.
     */
    public function surabayaDistricts(): Collection
    {
        return collect(array_keys($this->master()))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn(string $name) => (object) ['name' => $name]);
    }

    /**
     * Current Surabaya villages for a district. The bundled master is the
     * reliable UI source; database data is only used for an unknown/legacy
     * district so old records can still be inspected safely.
     */
    public function villages(?string $district = null): Collection
    {
        $district = $this->canonicalDistrict((string) $district);
        $master = $this->master();

        if ($district !== '' && array_key_exists($district, $master)) {
            return collect($master[$district])
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn(string $name) => (object) ['name' => $name]);
        }

        return Region::where('city', 'Surabaya')
            ->where('level', 'village')
            ->when($district, fn($query) => $query->where('district', $district))
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    public function isValidSurabayaLocation(string $district, string $village): bool
    {
        $district = $this->canonicalDistrict($district);
        $village = trim($village);
        $master = $this->master();

        if (array_key_exists($district, $master)) {
            return in_array($this->canonicalVillage($district, $village), $master[$district], true);
        }

        // Compatibility fallback for legacy/imported district names.
        return Region::where('city', 'Surabaya')
            ->where('level', 'village')
            ->where('district', $district)
            ->where('name', $village)
            ->where('active', true)
            ->exists();
    }

    public function canonicalDistrict(string $district): string
    {
        $district = trim($district);
        $aliases = [
            'Asemrowo' => 'Asem Rowo',
        ];

        return $aliases[$district] ?? $district;
    }

    public function canonicalVillage(string $district, string $village): string
    {
        $district = $this->canonicalDistrict($district);
        $village = trim($village);

        $aliases = [
            'Rungkut' => [
                'Kali Rungkut' => 'Kalirungkut',
                'Penjaringan Sari' => 'Penjaringansari',
            ],
            'Mulyorejo' => [
                'Kejawaan Putih Tambak' => 'Kejawan Putih Tambak',
            ],
            // Perak Utara + Perak Timur were merged into Tanjung Perak.
            'Pabean Cantian' => [
                'Perak Utara' => 'Tanjung Perak',
                'Perak Timur' => 'Tanjung Perak',
            ],
        ];

        return $aliases[$district][$village] ?? $village;
    }

    public function normalizeLocation(string $district, string $village): array
    {
        $district = $this->canonicalDistrict($district);

        return [
            'district' => $district,
            'village' => $this->canonicalVillage($district, $village),
        ];
    }

    /**
     * Resolve a selected map/GPS point to the bundled Surabaya master. External
     * reverse geocoding is only a suggestion layer; forms always retain manual
     * district/village controls when the service is unavailable or ambiguous.
     */
    public function reverseGeocode(float $latitude, float $longitude): ?array
    {
        if ($latitude < -7.38 || $latitude > -7.05 || $longitude < 112.55 || $longitude > 112.90) {
            return null;
        }

        $cacheKey = 'sirkel:reverse-region:'.number_format($latitude, 5, '.', '').':'.number_format($longitude, 5, '.', '');

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($latitude, $longitude) {
            try {
                $response = Http::acceptJson()
                    ->withHeaders([
                        'Accept-Language' => 'id',
                        'User-Agent' => 'SIRKEL/1.0 ('.config('app.url').')',
                    ])
                    ->timeout(max(3, (int) config('sirkel.reverse_geocoding.timeout', 8)))
                    ->retry(2, 1000, throw: false)
                    ->get((string) config('sirkel.reverse_geocoding.url'), [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 18,
                        'addressdetails' => 1,
                    ]);

                if (! $response->successful()) return null;
                $payload = $response->json();
                $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
                if ($address === []) return null;

                $cityText = implode(' ', array_filter([
                    $address['city'] ?? null,
                    $address['municipality'] ?? null,
                    $address['county'] ?? null,
                    $payload['display_name'] ?? null,
                ]));
                if (! str_contains(mb_strtolower($cityText), 'surabaya')) return null;

                $resolved = $this->resolveMasterLocation($address, (string) ($payload['display_name'] ?? ''));
                if (! $resolved) return null;

                return $resolved + [
                    'label' => mb_substr((string) ($payload['display_name'] ?? ''), 0, 300),
                    'source' => 'openstreetmap',
                ];
            } catch (Throwable $exception) {
                report($exception);
                return null;
            }
        });
    }

    /**
     * Synchronize only the Surabaya hierarchy to keep operational API usage low.
     * BinderByte remains an optional upstream refresh; forms also have the
     * bundled current Surabaya master as a resilient fallback.
     */
    public function syncFromBinderByte(): array
    {
        $key = config('sirkel.binderbyte.api_key');
        if (!$key) {
            return ['ok' => false, 'message' => 'BINDERBYTE_API_KEY belum diatur. Form tetap memakai master wilayah Surabaya bawaan SIRKEL.'];
        }

        try {
            $base = rtrim((string) config('sirkel.binderbyte.base_url'), '/');
            $provinceId = '35'; // Jawa Timur
            $cities = $this->get($base . '/wilayah/kabupaten', ['api_key' => $key, 'id_provinsi' => $provinceId]);
            $surabaya = collect($cities)->first(fn($item) => str_contains(strtoupper((string) ($item['name'] ?? '')), 'SURABAYA'));
            if (!$surabaya || empty($surabaya['id'])) {
                return ['ok' => false, 'message' => 'Kota Surabaya tidak ditemukan dari respons BinderByte.'];
            }

            $cityId = (string) $surabaya['id'];
            $districts = $this->get($base . '/wilayah/kecamatan', ['api_key' => $key, 'id_kabupaten' => $cityId]);
            $districtCount = 0;
            $villageCount = 0;

            DB::transaction(function () use ($base, $key, $cityId, $districts, &$districtCount, &$villageCount) {
                foreach ($districts as $district) {
                    if (empty($district['id']) || empty($district['name'])) {
                        continue;
                    }

                    $districtName = $this->canonicalDistrict($this->title((string) $district['name']));
                    Region::updateOrCreate(
                        ['external_id' => (string) $district['id'], 'level' => 'district'],
                        [
                            'parent_external_id' => $cityId,
                            'name' => $districtName,
                            'province' => 'Jawa Timur',
                            'city' => 'Surabaya',
                            'district' => $districtName,
                            'village' => null,
                            'active' => true,
                        ]
                    );
                    $districtCount++;

                    $villages = $this->get($base . '/wilayah/kelurahan', ['api_key' => $key, 'id_kecamatan' => (string) $district['id']]);
                    foreach ($villages as $village) {
                        if (empty($village['id']) || empty($village['name'])) {
                            continue;
                        }

                        $villageName = $this->canonicalVillage($districtName, $this->title((string) $village['name']));
                        Region::updateOrCreate(
                            ['external_id' => (string) $village['id'], 'level' => 'village'],
                            [
                                'parent_external_id' => (string) $district['id'],
                                'name' => $villageName,
                                'province' => 'Jawa Timur',
                                'city' => 'Surabaya',
                                'district' => $districtName,
                                'village' => $villageName,
                                'active' => true,
                            ]
                        );
                        $villageCount++;
                    }
                }
            });

            return ['ok' => true, 'message' => "Sinkronisasi BinderByte selesai: {$districtCount} kecamatan dan {$villageCount} kelurahan Surabaya diperbarui. Master bawaan tetap tersedia sebagai cadangan."];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => 'Sinkronisasi BinderByte gagal: ' . $exception->getMessage() . '. Form tetap memakai master wilayah Surabaya bawaan SIRKEL.'];
        }
    }

    private function master(): array
    {
        return (array) config('surabaya_regions', []);
    }

    private function resolveMasterLocation(array $address, string $displayName = ''): ?array
    {
        $master = $this->master();
        $values = collect($address)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values();
        if (trim($displayName) !== '') {
            $values->push(trim($displayName));
        }

        $districtCandidates = collect([
            $address['city_district'] ?? null,
            $address['district'] ?? null,
            $address['municipality'] ?? null,
            $address['county'] ?? null,
            $address['suburb'] ?? null,
        ])->filter()->merge($values)->values();

        $district = $this->matchMasterName($districtCandidates, array_keys($master));

        $villageCandidates = collect([
            $address['village'] ?? null,
            $address['suburb'] ?? null,
            $address['quarter'] ?? null,
            $address['neighbourhood'] ?? null,
            $address['hamlet'] ?? null,
        ])->filter()->merge($values)->values();

        if ($district) {
            $village = $this->matchMasterName($villageCandidates, $master[$district] ?? []);
            if ($village) {
                return $this->normalizeLocation($district, (string) $village);
            }
        }

        // Some providers omit the district but still mention an exact kelurahan
        // somewhere in the address/display label. Accept it only when the local
        // master maps that name to one unambiguous district.
        $matches = collect();
        foreach ($master as $districtName => $villages) {
            $village = $this->matchMasterName($villageCandidates, $villages);
            if ($village) {
                $matches->push(['district' => $districtName, 'village' => $village]);
            }
        }
        $matches = $matches->unique(fn ($match) => $match['district'].'|'.$match['village'])->values();
        if ($matches->count() === 1) {
            return $this->normalizeLocation($matches[0]['district'], $matches[0]['village']);
        }

        return null;
    }

    private function matchMasterName(Collection $candidates, array $masterNames): ?string
    {
        $names = collect($masterNames)
            ->map(fn ($name) => ['name' => (string) $name, 'key' => $this->locationKey((string) $name)])
            ->filter(fn ($item) => $item['key'] !== '')
            ->values();

        foreach ($candidates as $candidate) {
            $candidateKey = $this->locationKey((string) $candidate);
            if ($candidateKey === '') continue;
            $exact = $names->first(fn ($item) => $item['key'] === $candidateKey);
            if ($exact) return $exact['name'];
        }

        // Nominatim can put Kecamatan/Kelurahan only inside display_name or a
        // broader suburb label. A conservative containment pass makes GPS/link
        // lookup resilient without guessing across unrelated Surabaya regions.
        foreach ($candidates as $candidate) {
            $candidateKey = $this->locationKey((string) $candidate);
            if ($candidateKey === '') continue;
            $contained = $names
                ->filter(fn ($item) => strlen($item['key']) >= 5 && str_contains($candidateKey, $item['key']))
                ->sortByDesc(fn ($item) => strlen($item['key']))
                ->values();
            if ($contained->count() === 1) return $contained[0]['name'];
            if ($contained->count() > 1) {
                $longest = strlen($contained[0]['key']);
                $sameLength = $contained->filter(fn ($item) => strlen($item['key']) === $longest);
                if ($sameLength->count() === 1) return $contained[0]['name'];
            }
        }

        return null;
    }

    private function locationKey(string $value): string
    {
        $value = mb_strtolower(Str::ascii(trim($value)));
        $value = preg_replace('/\b(kecamatan|kelurahan|kota|surabaya)\b/u', '', $value) ?? $value;
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function get(string $url, array $query): array
    {
        $response = Http::acceptJson()->timeout(20)->retry(2, 500)->get($url, $query);
        $response->throw();
        $payload = $response->json();

        if (!($payload['result'] ?? false) || !is_array($payload['value'] ?? null)) {
            throw new \RuntimeException((string) ($payload['message'] ?? 'Respons BinderByte tidak valid'));
        }

        return $payload['value'];
    }

    private function title(string $value): string
    {
        return mb_convert_case(mb_strtolower(trim($value)), MB_CASE_TITLE, 'UTF-8');
    }
}
