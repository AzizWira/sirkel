<?php

namespace App\Http\Controllers;

use App\Models\{Asset, AssetCustody, PartnerTransfer};
use App\Services\AssetEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SplitBatchController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $profile = $request->user()->partnerProfile;
        abort_unless($profile, 403);

        $data = $request->validate([
            'parts' => 'required|array|min:2|max:10',
            'parts.*.quantity' => 'required|integer|min:1',
            'parts.*.condition_class' => 'required|string|max:60',
            'parts.*.verified_weight_kg' => 'required|numeric|min:0.001|max:9999',
        ]);

        DB::transaction(function () use ($request, $asset, $profile, $data) {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->tracking_type === 'batch', 422, 'Hanya kelompok barang yang dapat dipisahkan.');
            abort_unless($locked->core_locked_at, 422, 'Barang belum tercatat diterima secara fisik.');
            abort_if($locked->final_path, 422, 'Barang ini sudah memiliki hasil akhir.');
            abort_unless(in_array($locked->status, ['received', 'in_processing'], true), 422, 'Pisahkan kelompok hanya ketika barang sedang berada pada tahap penanganan aktif.');
            abort_if(
                PartnerTransfer::where('asset_id', $locked->id)->where('status', 'pending')->exists(),
                422,
                'Selesaikan atau batalkan pengalihan yang sedang berjalan sebelum memisahkan kelompok barang.'
            );

            $parentCustody = AssetCustody::where('asset_id', $locked->id)
                ->where('partner_profile_id', $profile->id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();
            abort_unless($parentCustody, 403, 'Barang ini tidak sedang berada dalam tanggung jawab mitra Anda.');
            abort_if(
                AssetCustody::where('asset_id', $locked->id)
                    ->whereNull('released_at')
                    ->where('partner_profile_id', '!=', $profile->id)
                    ->exists(),
                422,
                'Tanggung jawab barang tidak konsisten. Muat ulang halaman sebelum melanjutkan.'
            );

            $quantity = collect($data['parts'])->sum('quantity');
            abort_unless(
                (int) $quantity === (int) $locked->quantity,
                422,
                'Jumlah barang pada kelompok hasil harus sama dengan kelompok asal.'
            );

            $weight = (float) collect($data['parts'])->sum('verified_weight_kg');
            if ($locked->verified_weight_kg !== null) {
                $tolerance = max(0.01, (float) $locked->verified_weight_kg * 0.03);
                abort_if(
                    abs($weight - (float) $locked->verified_weight_kg) > $tolerance,
                    422,
                    'Total berat kelompok hasil harus mendekati berat terverifikasi kelompok asal (toleransi 3%).'
                );
            }

            foreach ($data['parts'] as $part) {
                $child = Asset::create($locked->only([
                    'owner_user_id',
                    'device_category_id',
                    'custom_item_name',
                    'brand',
                    'model_name',
                    'description',
                    'origin_district',
                    'origin_village',
                    'handover_type',
                ]) + [
                    'parent_asset_id' => $locked->id,
                    'passport_code' => 'SRK-B-' . now()->format('ymd') . '-' . strtoupper(str()->random(6)),
                    'tracking_type' => 'batch',
                    'quantity' => (int) $part['quantity'],
                    'condition_class' => $part['condition_class'],
                    'verified_weight_kg' => (float) $part['verified_weight_kg'],
                    'preliminary_path' => $locked->preliminary_path,
                    'status' => 'received',
                    'core_locked_at' => now(),
                ]);

                AssetCustody::create([
                    'asset_id' => $child->id,
                    'partner_profile_id' => $profile->id,
                    'received_by_user_id' => $request->user()->id,
                    'received_at' => now(),
                ]);

                app(AssetEventService::class)->add(
                    $child,
                    'BATCH_SPLIT_CREATED',
                    'Kelompok hasil dibuat',
                    'Dibentuk dari ' . $locked->passport_code . '.',
                    ['parent' => $locked->passport_code]
                );
            }

            $parentCustody->update(['released_at' => now()]);
            $locked->update([
                'status' => 'closed',
                'final_path' => 'SPLIT_TO_SUB_BATCHES',
            ]);
            app(AssetEventService::class)->add(
                $locked,
                'BATCH_SPLIT',
                'Kelompok barang dipisahkan',
                'Kelompok induk ditutup dan penanganan dilanjutkan melalui kelompok hasil.'
            );
        });

        return back()->with('success', 'Kelompok barang berhasil dipisahkan. Setiap kelompok hasil memiliki Paspor dan riwayat penanganan sendiri.');
    }
}
