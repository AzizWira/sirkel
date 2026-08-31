<?php

namespace App\Http\Controllers;

use App\Models\{Asset, HandoverRequest, Offer};
use App\Services\{AssetEventService, NotificationService, OfferLifecycleService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function respond(Request $request, Offer $offer)
    {
        $handover = $offer->request;
        abort_unless($handover->user_id === auth()->id(), 403);
        app(OfferLifecycleService::class)->refresh($handover);

        $data = $request->validate([
            'decision' => 'required|in:accept,reject',
            'rejection_reason' => 'nullable|required_if:decision,reject|string|max:100',
            'rejection_note' => 'nullable|string|max:500',
        ]);

        $decision = $data['decision'];
        DB::transaction(function () use ($offer, $handover, $data, $decision) {
            $lockedOffer = Offer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $lockedRequest = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::whereKey($lockedRequest->asset_id)->lockForUpdate()->firstOrFail();

            abort_unless($lockedRequest->user_id === auth()->id(), 403);
            abort_unless($lockedRequest->effectiveHandoverType() === 'sale', 422, 'Penawaran nilai hanya berlaku untuk penyerahan dengan penawaran nilai.');
            abort_unless($lockedOffer->is_current && $lockedOffer->status === 'waiting_user', 422, 'Penawaran ini sudah tidak aktif.');
            abort_if($lockedOffer->expires_at?->isPast(), 422, 'Penawaran sudah kedaluwarsa. Muat ulang halaman untuk melihat status terbaru.');
            abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra; penawaran awal tidak dapat diubah lagi.');

            if ($decision === 'accept') {
                $lockedOffer->update([
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]);
                $scheduleStatus = $lockedRequest->schedule_status;
                if ($lockedRequest->requested_date && $scheduleStatus !== 'proposed') {
                    $scheduleStatus = 'accepted';
                }
                $lockedRequest->update([
                    'status' => 'offer_accepted',
                    'schedule_status' => $scheduleStatus,
                ]);
                $asset->update(['status' => 'scheduled']);
                app(AssetEventService::class)->add(
                    $asset,
                    'OFFER_ACCEPTED',
                    'Penawaran diterima',
                    'Nilai awal disepakati sebagai estimasi penyerahan, bukan pembayaran di dalam SIRKEL.'
                );
            } else {
                $lockedOffer->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                    'is_current' => false,
                    'user_rejection_reason' => $data['rejection_reason'],
                    'user_rejection_note' => $data['rejection_note'] ?? null,
                ]);
                $lockedRequest->update(['status' => 'offer_rejected']);
                $asset->update(['status' => 'offer_rejected']);
                app(AssetEventService::class)->add(
                    $asset,
                    'OFFER_REJECTED',
                    'Penawaran ditolak',
                    $data['rejection_reason'] . (!empty($data['rejection_note']) ? ': ' . $data['rejection_note'] : '')
                );
            }
        });

        app(NotificationService::class)->send(
            $handover->partner->user,
            'Respons penawaran SIRKEL',
            'Warga ' . ($decision === 'accept' ? 'menerima' : 'menolak') . ' penawaran untuk ' . $handover->asset->passport_code,
            route('partner.requests.show', $handover)
        );

        return redirect()->route('user.assets.show', $handover->asset)
            ->with('success', $decision === 'accept'
                ? 'Penawaran diterima. Lanjutkan ke jadwal dan serah terima barang.'
                : 'Penawaran ditolak. Pilih apakah ingin meminta penawaran baru dari mitra yang sama, mengganti mitra, atau membatalkan penyerahan.');
    }

    public function confirmFinal(Request $request, Offer $offer)
    {
        $handover = $offer->request;
        abort_unless($handover->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'decision' => 'required|in:accept,reject',
            'reason' => 'nullable|required_if:decision,reject|string|max:500',
        ]);

        DB::transaction(function () use ($request, $offer, $handover, $data) {
            $lockedOffer = Offer::whereKey($offer->id)->lockForUpdate()->firstOrFail();
            $lockedRequest = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->user_id === $request->user()->id, 403);
            abort_unless($lockedRequest->effectiveHandoverType() === 'sale', 422, 'Nilai akhir hanya berlaku untuk penyerahan dengan penawaran nilai.');
            abort_unless($lockedOffer->final_agreed_value !== null, 422, 'Nilai akhir belum dicatat mitra.');

            if ($data['decision'] === 'accept') {
                if (!$lockedOffer->final_confirmed_at) {
                    $lockedOffer->update(['final_confirmed_at' => now()]);
                    app(AssetEventService::class)->add(
                        $lockedRequest->asset,
                        'FINAL_VALUE_CONFIRMED',
                        'Nilai akhir dikonfirmasi warga',
                        'Nilai dicatat sebagai histori valuasi; pembayaran tetap di luar SIRKEL.'
                    );
                }
                return;
            }

            abort_if($lockedOffer->final_confirmed_at, 422, 'Nilai akhir sudah dikonfirmasi dan tidak dapat ditolak melalui aksi yang sama. Gunakan Laporkan Masalah bila ada sengketa.');
            $lockedOffer->update([
                'final_value_reason' => trim(($lockedOffer->final_value_reason ? $lockedOffer->final_value_reason . ' | ' : '') . 'Ditolak warga: ' . $data['reason']),
                'final_confirmed_at' => null,
            ]);
            app(AssetEventService::class)->add(
                $lockedRequest->asset,
                'FINAL_VALUE_REJECTED',
                'Nilai akhir belum disepakati',
                $data['reason']
            );
        });

        return back()->with('success', $data['decision'] === 'accept'
            ? 'Nilai akhir dikonfirmasi.'
            : 'Keberatan nilai akhir tercatat. Hubungi mitra atau gunakan Laporkan Masalah bila diperlukan.');
    }
}
