<?php

namespace App\Http\Controllers;

use App\Models\{Asset, DonationProof, HandoverRequest};
use App\Services\{AssetEventService, NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonationProofController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $profile = $request->user()->partnerProfile;
        abort_unless($profile && $profile->verification_status === 'approved', 403, 'Mitra belum terverifikasi.');

        $data = $request->validate([
            'recipient_type' => 'required|in:individual,community,school,foundation,other',
            'recipient_name' => 'nullable|string|max:160',
            'recipient_note' => 'nullable|string|max:800',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_accuracy_m' => 'nullable|numeric|min:0|max:99999',
            'location_label' => 'nullable|string|max:180',
            'donated_at' => 'required|date|before_or_equal:now',
        ]);

        if ($data['recipient_type'] !== 'individual' && blank($data['recipient_name'] ?? null)) {
            throw ValidationException::withMessages(['recipient_name' => 'Nama penerima/organisasi diperlukan untuk jenis penerima ini.']);
        }

        $proof = DB::transaction(function () use ($request, $asset, $profile, $data) {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->custody()->where('partner_profile_id', $profile->id)->whereNull('released_at')->exists(), 403, 'Barang ini tidak sedang berada dalam tanggung jawab mitra Anda.');
            abort_unless($locked->status === 'awaiting_donation_proof' && !$locked->final_path, 422, 'Barang belum berada pada tahap bukti penyaluran donasi.');
            abort_if(DonationProof::where('asset_id', $locked->id)->exists(), 422, 'Bukti donasi untuk barang ini sudah tercatat.');

            $path = $request->file('photo')->store('donation-proofs/' . $locked->public_id, 'public');
            $proof = DonationProof::create([
                'asset_id' => $locked->id,
                'partner_profile_id' => $profile->id,
                'submitted_by_user_id' => $request->user()->id,
                'recipient_type' => $data['recipient_type'],
                'recipient_name' => filled($data['recipient_name'] ?? null) ? trim((string) $data['recipient_name']) : null,
                'recipient_note' => filled($data['recipient_note'] ?? null) ? trim((string) $data['recipient_note']) : null,
                'photo_path' => $path,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'location_accuracy_m' => $data['location_accuracy_m'] ?? null,
                'location_label' => filled($data['location_label'] ?? null) ? trim((string) $data['location_label']) : null,
                'donated_at' => $data['donated_at'],
                'status' => 'recorded',
            ]);

            $locked->update(['final_path' => 'DONATED', 'status' => 'donated']);
            $locked->custody()->where('partner_profile_id', $profile->id)->whereNull('released_at')->update([
                'released_at' => now(),
                'release_evidence_path' => $path,
            ]);

            HandoverRequest::query()
                ->where('asset_id', $locked->id)
                ->where('partner_profile_id', $profile->id)
                ->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)
                ->update(['status' => 'completed']);

            app(AssetEventService::class)->add(
                $locked,
                'DONATION_PROOF_RECORDED',
                'Bukti penyaluran donasi tercatat',
                'Mitra mencatat foto, waktu, dan lokasi saat barang disalurkan kepada penerima akhir.',
                [
                    'path' => 'DONATED',
                    'proof_public_id' => $proof->public_id,
                    'recipient_type' => $proof->recipient_type,
                    'recipient_name' => $proof->recipient_type === 'individual' ? null : $proof->recipient_name,
                ]
            );
            app(AssetEventService::class)->add($locked, 'VERIFIED_OUTCOME', 'Hasil akhir sirkular terverifikasi', 'Didonasikan', ['path' => 'DONATED']);

            return $proof;
        });

        app(NotificationService::class)->send(
            $asset->owner,
            'Barang telah disalurkan untuk donasi',
            'Bukti penyaluran ' . $asset->passport_code . ' sudah dicatat oleh mitra. Anda dapat melihat foto dan informasi penyalurannya pada detail barang.',
            route('user.assets.show', $asset)
        );

        return redirect()->route('partner.assets.show', $asset)
            ->with('success', 'Bukti donasi tersimpan. Barang sekarang tercatat selesai didonasikan.');
    }
}
