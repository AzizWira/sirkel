<?php
namespace App\Services;

use App\Models\{Asset, DeviceCategory, PartnerProfile};
use Illuminate\Support\Collection;

class PartnerMatchingService
{
    public function match(
        Asset $asset,
        string $method,
        float $lat,
        float $lng,
        ?string $handoverType = null,
        ?string $district = null
    ): Collection {
        $capability = app(AssetFlowService::class)->initialCapability($asset);
        $coverageIds = $this->categoryCoverageIds($asset);

        $query = PartnerProfile::query()
            ->with(['user', 'capabilities', 'acceptedCategories'])
            ->where('verification_status', 'approved')
            ->where('admin_status', 'active')
            ->where('accepting_requests', true)
            ->whereHas('capabilities', fn($q) => $q->where('capability', $capability)->where('status', 'approved'))
            ->whereHas('acceptedCategories', fn($q) => $q->whereIn('device_categories.id', $coverageIds));

        if ($method === 'pickup') {
            $query->whereHas('capabilities', fn($q) => $q->where('capability', 'pickup')->where('status', 'approved'));
        }

        $results = $query->get()->map(function ($partner) use ($lat, $lng, $method, $capability, $district, $asset) {
            $distance = $this->haversine($lat, $lng, (float) $partner->latitude, (float) $partner->longitude);
            $partner->match_distance_km = round($distance, 2);
            $partner->within_radius = $method !== 'pickup' || $distance <= (float) $partner->pickup_radius_km;
            $partner->matched_capability = $capability;
            $partner->same_district = filled($district)
                && mb_strtolower(trim((string) $partner->district)) === mb_strtolower(trim((string) $district));
            $partner->category_match_type = $partner->acceptedCategories->contains('id', $asset->device_category_id)
                ? 'exact'
                : 'group';

            return $partner;
        });

        $ranked = $results->sort(function ($left, $right) use ($method) {
            if ($method === 'pickup' && $left->within_radius !== $right->within_radius) {
                return $left->within_radius ? -1 : 1;
            }
            if (($left->category_match_type ?? 'exact') !== ($right->category_match_type ?? 'exact')) {
                return ($left->category_match_type ?? 'exact') === 'exact' ? -1 : 1;
            }

            $leftScore = max(0, (float) $left->match_distance_km - ($left->same_district ? 0.35 : 0));
            $rightScore = max(0, (float) $right->match_distance_km - ($right->same_district ? 0.35 : 0));

            return $leftScore <=> $rightScore;
        })->values();

        return $this->markRecommendation($ranked, $method, $capability, $district);
    }

    public function assistanceCandidates(
        Asset $asset,
        string $method,
        float $lat,
        float $lng,
        ?string $district = null
    ): Collection {
        $capability = app(AssetFlowService::class)->initialCapability($asset);
        $coverageIds = $this->categoryCoverageIds($asset);

        $query = PartnerProfile::query()
            ->with(['user', 'capabilities', 'acceptedCategories'])
            ->where('verification_status', 'approved')
            ->where('admin_status', 'active')
            ->where('accepting_requests', true)
            ->whereHas('capabilities', fn($q) => $q->where('capability', $capability)->where('status', 'approved'));

        if ($method === 'pickup') {
            $query->whereHas('capabilities', fn($q) => $q->where('capability', 'pickup')->where('status', 'approved'));
        }

        return $query->get()->map(function ($partner) use ($asset, $coverageIds, $lat, $lng, $method, $district, $capability) {
            $distance = $this->haversine($lat, $lng, (float) $partner->latitude, (float) $partner->longitude);
            $partner->match_distance_km = round($distance, 2);
            $partner->within_radius = $method !== 'pickup' || $distance <= (float) $partner->pickup_radius_km;
            $partner->matched_capability = $capability;
            $partner->same_district = filled($district)
                && mb_strtolower(trim((string) $partner->district)) === mb_strtolower(trim((string) $district));
            $partner->category_supported = $partner->acceptedCategories->whereIn('id', $coverageIds)->isNotEmpty();
            $partner->category_match_type = $partner->acceptedCategories->contains('id', $asset->device_category_id)
                ? 'exact'
                : ($partner->category_supported ? 'group' : 'assisted');

            return $partner;
        })->sort(function ($left, $right) use ($method) {
            if ((bool) $left->category_supported !== (bool) $right->category_supported) {
                return $left->category_supported ? -1 : 1;
            }
            if ($method === 'pickup' && $left->within_radius !== $right->within_radius) {
                return $left->within_radius ? -1 : 1;
            }
            if ($left->same_district !== $right->same_district) {
                return $left->same_district ? -1 : 1;
            }

            return (float) $left->match_distance_km <=> (float) $right->match_distance_km;
        })->values();
    }

    public function supportsAssistedRequest(PartnerProfile $partner, Asset $asset, string $method): bool
    {
        if ($partner->verification_status !== 'approved' || ($partner->admin_status ?? 'inactive') !== 'active' || !$partner->accepting_requests) {
            return false;
        }

        $capability = app(AssetFlowService::class)->initialCapability($asset);
        if (!$partner->hasApprovedCapability($capability)) {
            return false;
        }
        if ($method === 'pickup' && !$partner->hasApprovedCapability('pickup')) {
            return false;
        }

        // Bantuan admin boleh meminta mitra meninjau kategori di luar cakupan rutin,
        // tetapi layanan utama yang dibutuhkan barang tetap tidak boleh dilewati.
        return true;
    }

    public function rankTransferTargets(Collection $partners, PartnerProfile $source, string $requiredCapability, ?Asset $asset = null): Collection
    {
        $ranked = $partners->map(function ($partner) use ($source, $requiredCapability, $asset) {
            $distance = $this->haversine(
                (float) $source->latitude,
                (float) $source->longitude,
                (float) $partner->latitude,
                (float) $partner->longitude
            );

            $partner->transfer_distance_km = round($distance, 2);
            $partner->matched_capability = $requiredCapability;
            $partner->same_district_as_source = mb_strtolower(trim((string) $partner->district))
                === mb_strtolower(trim((string) $source->district));
            if ($asset) {
                $partner->category_match_type = $partner->relationLoaded('acceptedCategories')
                    && $partner->acceptedCategories->contains('id', $asset->device_category_id)
                    ? 'exact'
                    : 'group';
            }

            return $partner;
        })->sort(function ($left, $right) {
            if (($left->category_match_type ?? 'exact') !== ($right->category_match_type ?? 'exact')) {
                return ($left->category_match_type ?? 'exact') === 'exact' ? -1 : 1;
            }

            $leftScore = max(0, (float) $left->transfer_distance_km - ($left->same_district_as_source ? 0.35 : 0));
            $rightScore = max(0, (float) $right->transfer_distance_km - ($right->same_district_as_source ? 0.35 : 0));

            return $leftScore <=> $rightScore;
        })->values();

        return $ranked->map(function ($partner, $index) use ($requiredCapability) {
            $partner->is_recommended = $index === 0;
            $partner->recommendation_reason = null;

            if ($index === 0) {
                $distance = number_format((float) $partner->transfer_distance_km, 1, ',', '.');
                $service = \App\Support\SirkelUi::label($requiredCapability);
                $sameDistrict = $partner->same_district_as_source && $partner->district;
                $coverageNote = ($partner->category_match_type ?? 'exact') === 'group'
                    ? ' Cakupan kategori umum mitra juga sesuai dengan kelompok barang ini.'
                    : '';

                $partner->recommendation_reason = ($sameDistrict
                    ? "Berada di {$partner->district} dan termasuk yang terdekat dari lokasi mitra Anda untuk layanan {$service} ({$distance} km)."
                    : "Mitra terdekat dari lokasi mitra Anda untuk layanan {$service} ({$distance} km).")
                    . $coverageNote;
            }

            return $partner;
        });
    }

    public function supportsExistingRequest(PartnerProfile $partner, Asset $asset, string $method): bool
    {
        if ($partner->verification_status !== 'approved' || ($partner->admin_status ?? 'inactive') !== 'active' || !$partner->accepting_requests) {
            return false;
        }

        $capability = app(AssetFlowService::class)->initialCapability($asset);
        if (!$partner->hasApprovedCapability($capability)) {
            return false;
        }
        if ($method === 'pickup' && !$partner->hasApprovedCapability('pickup')) {
            return false;
        }

        return $this->supportsCategory($partner, $asset);
    }

    public function supportsCategory(PartnerProfile $partner, Asset $asset): bool
    {
        return $partner->acceptedCategories()
            ->whereIn('device_categories.id', $this->categoryCoverageIds($asset))
            ->exists();
    }

    public function categoryCoverageIds(Asset $asset): array
    {
        $asset->loadMissing('category.group');
        $ids = [(int) $asset->device_category_id];
        $groupCode = $asset->category?->group?->code;
        $fallbackCode = $groupCode ? config('sirkel_catalog.group_fallbacks.' . $groupCode) : null;

        if ($fallbackCode && $fallbackCode !== $asset->category?->code) {
            $fallbackId = DeviceCategory::query()->where('code', $fallbackCode)->where('active', true)->value('id');
            if ($fallbackId) {
                $ids[] = (int) $fallbackId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function markRecommendation(Collection $partners, string $method, string $capability, ?string $district): Collection
    {
        return $partners->map(function ($partner, $index) use ($method, $capability, $district) {
            $partner->is_recommended = $index === 0;
            $partner->recommendation_reason = null;

            if ($index !== 0) {
                return $partner;
            }

            $distance = number_format((float) $partner->match_distance_km, 1, ',', '.');
            $service = \App\Support\SirkelUi::label($capability);
            $coverageNote = ($partner->category_match_type ?? 'exact') === 'group'
                ? ' Mitra juga menerima cakupan umum untuk kelompok kategori barang ini.'
                : '';

            if ($method === 'pickup' && $partner->within_radius) {
                $partner->recommendation_reason = ($partner->same_district && $district
                    ? "Berada di {$district}, dalam radius penjemputan, dan termasuk yang terdekat untuk layanan {$service} ({$distance} km)."
                    : "Dalam radius penjemputan dan termasuk yang terdekat untuk layanan {$service} ({$distance} km).")
                    . $coverageNote;
            } elseif ($method === 'pickup') {
                $partner->recommendation_reason = "Mitra terdekat yang tersedia untuk layanan {$service} ({$distance} km), tetapi berada di luar radius penjemputan reguler." . $coverageNote;
            } else {
                $partner->recommendation_reason = ($partner->same_district && $district
                    ? "Berada di {$district} dan termasuk yang terdekat untuk layanan {$service} ({$distance} km)."
                    : "Termasuk mitra terdekat untuk layanan {$service} ({$distance} km).")
                    . $coverageNote;
            }

            return $partner;
        });
    }
}
