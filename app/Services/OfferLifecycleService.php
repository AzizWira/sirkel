<?php

namespace App\Services;

use App\Models\{HandoverRequest, Offer};
use Illuminate\Support\Facades\DB;

class OfferLifecycleService
{
    public function expireOverdue(?int $handoverRequestId = null): int
    {
        $query = Offer::query()
            ->where('is_current', true)
            ->where('status', 'waiting_user')
            ->where('expires_at', '<=', now());

        if ($handoverRequestId) {
            $query->where('handover_request_id', $handoverRequestId);
        }

        $ids = $query->orderBy('id')->pluck('id');
        $expired = 0;

        foreach ($ids as $offerId) {
            $didExpire = DB::transaction(function () use ($offerId) {
                $offer = Offer::whereKey($offerId)->lockForUpdate()->first();
                if (!$offer || !$offer->is_current || $offer->status !== 'waiting_user' || !$offer->expires_at?->isPast()) {
                    return false;
                }

                $handover = HandoverRequest::whereKey($offer->handover_request_id)->lockForUpdate()->first();
                if (!$handover) {
                    return false;
                }

                $offer->update([
                    'status' => 'expired',
                    'is_current' => false,
                ]);

                // Expiry hanya mengakhiri versi penawaran, bukan permintaan penyerahan.
                // Mitra dapat mengirim penawaran baru tanpa memaksa warga membuat request baru.
                if ($handover->status === 'offered') {
                    $handover->update(['status' => 'accepted']);
                    if (!$handover->asset->core_locked_at && !$handover->asset->final_path) {
                        $handover->asset->update(['status' => 'partner_accepted']);
                    }
                }

                app(AssetEventService::class)->add(
                    $handover->asset,
                    'OFFER_EXPIRED',
                    'Penawaran kedaluwarsa',
                    'Penawaran versi ' . $offer->version . ' berakhir tanpa respons. Mitra dapat mengirim penawaran baru.'
                );

                return true;
            });

            if ($didExpire) {
                $expired++;
                $offer = Offer::with(['request.user', 'request.partner.user', 'request.asset'])->find($offerId);
                if ($offer?->request) {
                    app(NotificationService::class)->send(
                        $offer->request->user,
                        'Penawaran telah berakhir',
                        'Penawaran untuk ' . $offer->request->asset->passport_code . ' sudah kedaluwarsa. Mitra dapat mengirim penawaran baru.',
                        route('user.assets.show', $offer->request->asset)
                    );
                    app(NotificationService::class)->send(
                        $offer->request->partner->user,
                        'Penawaran kedaluwarsa',
                        'Penawaran untuk ' . $offer->request->asset->passport_code . ' berakhir tanpa respons. Anda dapat membuat penawaran baru.',
                        route('partner.requests.show', $offer->request)
                    );
                }
            }
        }

        return $expired;
    }

    public function refresh(HandoverRequest $handover): void
    {
        $this->expireOverdue($handover->id);
        $handover->refresh();
    }
}
