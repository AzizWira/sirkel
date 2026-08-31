<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PartnerProfile;

class ImpactService
{
    public function summary(?int $partnerId = null, ?int $ownerUserId = null): array
    {
        $q = Asset::query()->whereNotNull('verified_weight_kg');
        if ($ownerUserId)
            $q->where('owner_user_id', $ownerUserId);
        if ($partnerId) {
            $userId = PartnerProfile::find($partnerId)?->user_id;
            if ($userId)
                $q->whereHas('assessments', fn($x) => $x->where('assessment_type', 'partner')->where('assessor_user_id', $userId));
        }
        $all = $q->get();
        $sum = fn($paths) => $all->whereIn('final_path', (array) $paths)->sum('verified_weight_kg');
        return [
            'verified_kg' => round($all->sum('verified_weight_kg'), 3),
            'verified_assets' => $all->count(),
            'repair_kg' => round($sum(['REPAIRED']), 3),
            'reuse_kg' => round($sum(['REUSED']), 3),
            'donation_kg' => round($sum(['DONATED']), 3),
            'parts_kg' => round($sum(['PARTS_RECOVERED']), 3),
            'recovery_kg' => round($sum(['RECOVERY_CONFIRMED']), 3),
            'unverified_kg' => round($sum(['UNVERIFIED_FINAL_TREATMENT', 'RECEIVED_BY_RECOVERY_PARTNER']), 3),
        ];
    }
}
