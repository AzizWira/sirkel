<?php

namespace App\Services;

use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
