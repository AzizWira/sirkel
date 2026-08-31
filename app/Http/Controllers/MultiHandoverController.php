<?php

namespace App\Http\Controllers;

use App\Models\{Asset, HandoverRequest, IntakeSession, PartnerProfile};
use App\Services\{AssetEventService, IntakeSessionStateService, NotificationService, PartnerMatchingService, RegionService};
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MultiHandoverController extends Controller
{
    public function form(Request $request, IntakeSession $session)
    {
        $this->assertReviewSession($session, $request);
        $session->load(['items.asset.category.group', 'items.asset.requests']);
        $items = app(IntakeSessionStateService::class)->actionableItems($session);

        return view('user.handovers.multi-form', [
            'session' => $session,
            'items' => $items,
            'districts' => app(RegionService::class)->surabayaDistricts(),
            'saved' => (array) ($session->handover_context_json ?? []),
        ]);
    }

    public function match(Request $request, IntakeSession $session)
    {
        $this->assertReviewSession($session, $request);
        $session->load(['items.asset.requests']);
        $items = app(IntakeSessionStateService::class)->actionableItems($session);
        $data = $this->validateContext($request, $items);
        $session->update(['handover_context_json' => $data]);

        return redirect()->route('user.intake.handover.partners', $session);
    }

    public function partners(Request $request, IntakeSession $session)
    {
        $this->assertReviewSession($session, $request);
        $session->load(['items.asset.category.group', 'items.asset.requests']);
        $items = app(IntakeSessionStateService::class)->actionableItems($session);
        $context = (array) ($session->handover_context_json ?? []);
        if (! $context) {
            return redirect()->route('user.intake.handover.form', $session)
                ->with('info', 'Atur cara penyerahan terlebih dahulu.');
        }

        $matcher = app(PartnerMatchingService::class);
        $byAsset = [];
        foreach ($items as $item) {
            $asset = $item->asset;
            $type = (string) ($context['handover_types'][$asset->public_id] ?? '');
            if ($type === '') {
                $byAsset[$asset->public_id] = collect();
                continue;
            }
            $byAsset[$asset->public_id] = $matcher->match(
                $asset,
                $context['method'],
                (float) $context['latitude'],
                (float) $context['longitude'],
                $type,
                $context['district']
            )->values();
        }

        $commonIds = null;
        foreach ($byAsset as $partners) {
            $ids = $partners->pluck('id')->all();
            $commonIds = $commonIds === null ? $ids : array_values(array_intersect($commonIds, $ids));
        }
        $commonPartners = collect();
        if ($commonIds) {
            $first = collect(reset($byAsset));
            $commonPartners = $first->whereIn('id', $commonIds)->values();
        }

        $hasUnavailablePartner = collect($byAsset)->contains(function ($partners) {
            return $partners->isEmpty();
        });

        $commonWhatsappByPartner = [];
        foreach ($commonPartners as $partner) {
            $commonWhatsappByPartner[$partner->public_id] = \App\Support\SirkelUi::whatsappUrl(
                $partner->phone,
                'Halo '.$partner->business_name.', saya ingin bertanya terkait penyerahan beberapa barang melalui SIRKEL.'
            );
        }

        $partnerWhatsappByAsset = [];
        foreach ($items as $item) {
            $asset = $item->asset;
            foreach ($byAsset[$asset->public_id] as $partner) {
                $partnerWhatsappByAsset[$asset->public_id][$partner->public_id] = \App\Support\SirkelUi::whatsappUrl(
                    $partner->phone,
                    'Halo '.$partner->business_name.', saya ingin bertanya terkait '.$asset->passport_code.' di SIRKEL.'
                );
            }
        }

        return view('user.handovers.multi-partners', [
            'session' => $session,
            'items' => $items,
            'context' => $context,
            'partnersByAsset' => $byAsset,
            'commonPartners' => $commonPartners,
            'hasUnavailablePartner' => $hasUnavailablePartner,
            'commonWhatsappByPartner' => $commonWhatsappByPartner,
            'partnerWhatsappByAsset' => $partnerWhatsappByAsset,
        ]);
    }

    public function create(Request $request, IntakeSession $session)
    {
        $this->assertReviewSession($session, $request);
        $session->load(['items.asset.requests']);
        $items = app(IntakeSessionStateService::class)->actionableItems($session);
        $context = (array) ($session->handover_context_json ?? []);
        abort_unless($context, 422, 'Rencana penyerahan belum tersedia.');

        $data = $request->validate([
            'common_partner' => 'nullable|string|size:26',
            'partners' => 'nullable|array',
            'partners.*' => 'nullable|string|size:26',
            'ownership_acknowledgement' => 'required|accepted',
        ]);

        $matcher = app(PartnerMatchingService::class);
        $selections = [];
        foreach ($items as $item) {
            $asset = $item->asset;
            $selectedPublicId = $data['common_partner'] ?? ($data['partners'][$asset->public_id] ?? null);
            if (! $selectedPublicId) {
                throw ValidationException::withMessages(['partners' => 'Pilih mitra untuk setiap kelompok barang.']);
            }
            $partner = PartnerProfile::where('public_id', $selectedPublicId)->first();
            if (! $partner) {
                throw ValidationException::withMessages(['partners' => 'Salah satu mitra tidak lagi tersedia. Muat ulang rencana mitra.']);
            }
            $type = (string) ($context['handover_types'][$asset->public_id] ?? '');
            $valid = $matcher->match(
                $asset,
                $context['method'],
                (float) $context['latitude'],
                (float) $context['longitude'],
                $type,
                $context['district']
            )->contains(fn ($candidate) => (int) $candidate->id === (int) $partner->id);
            if (! $valid) {
                throw ValidationException::withMessages(['partners' => $partner->business_name.' tidak lagi sesuai untuk '.$asset->passport_code.'. Muat ulang halaman.']);
            }
            $selections[] = [$asset, $partner, $type];
        }

        $handovers = DB::transaction(function () use ($request, $session, $context, $selections, $matcher) {
            $created = [];
            foreach ($selections as [$asset, $partner, $type]) {
                $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->final_path || $locked->core_locked_at, 422, 'Salah satu barang sudah tidak dapat membuat penyerahan baru.');
                abort_if($locked->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists(), 422, 'Salah satu barang sudah memiliki penyerahan aktif.');

                $distance = $matcher->haversine(
                    (float) $context['latitude'], (float) $context['longitude'],
                    (float) $partner->latitude, (float) $partner->longitude
                );
                $pickup = $context['method'] === 'pickup';
                $handover = HandoverRequest::create([
                    'asset_id' => $locked->id,
                    'user_id' => $request->user()->id,
                    'partner_profile_id' => $partner->id,
                    'method' => $context['method'],
                    'handover_type' => $type,
                    'ownership_acknowledged_at' => now(),
                    'status' => 'pending',
                    'pickup_latitude' => $context['latitude'],
                    'pickup_longitude' => $context['longitude'],
                    'pickup_address' => $pickup ? ($context['address'] ?? null) : null,
                    'pickup_district' => $context['district'],
                    'pickup_village' => $context['village'],
                    'distance_km' => round($distance, 2),
                    'within_radius' => ! $pickup || $distance <= (float) $partner->pickup_radius_km,
                    'outside_radius' => $pickup && $distance > (float) $partner->pickup_radius_km,
                    'requested_date' => $context['requested_date'] ?? null,
                    'requested_time_start' => $context['time_start'] ?? null,
                    'requested_time_end' => $context['time_end'] ?? null,
                    'schedule_status' => 'requested',
                ]);
                $locked->update(['status' => 'requested', 'handover_type' => $type]);
                app(AssetEventService::class)->add($locked, 'HANDOVER_REQUESTED', 'Permintaan penyerahan dibuat', 'Dibuat bersama rencana penyerahan multi-barang.', [
                    'method' => $context['method'], 'handover_type' => $type, 'intake_session' => $session->public_id,
                ]);
                $created[] = $handover;
            }
            return $created;
        });

        app(IntakeSessionStateService::class)->reconcile($session);

        foreach ($handovers as $handover) {
            app(NotificationService::class)->send(
                $handover->partner->user,
                'Permintaan baru SIRKEL',
                'Ada barang '.$handover->asset->passport_code.' yang menunggu respons.',
                route('partner.requests.show', $handover)
            );
        }

        return redirect()->route('user.activity')->with('success', count($handovers).' permintaan penyerahan berhasil dikirim. Data lokasi dan jadwal cukup diisi satu kali untuk rencana ini.');
    }

    private function validateContext(Request $request, $items): array
    {
        $rules = [
            'method' => 'required|in:pickup,dropoff',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|required_if:method,pickup|string|max:500',
            'district' => 'required|string|max:100',
            'village' => 'required|string|max:100',
            'requested_date' => 'required|date|after_or_equal:today',
            'time_start' => 'nullable|required_if:method,pickup|date_format:H:i',
            'time_end' => 'nullable|required_if:method,pickup|date_format:H:i|after:time_start',
            'handover_types' => 'required|array',
        ];
        foreach ($items as $item) {
            $rules['handover_types.'.$item->asset->public_id] = 'required|in:sale,free_handover,donation';
        }
        $data = $request->validate($rules);

        $regions = app(RegionService::class);
        if (! $regions->isValidSurabayaLocation($data['district'], $data['village'])) {
            throw ValidationException::withMessages(['village' => 'Kelurahan tidak sesuai dengan kecamatan yang dipilih.']);
        }
        $normalized = $regions->normalizeLocation($data['district'], $data['village']);
        $data['district'] = $normalized['district'];
        $data['village'] = $normalized['village'];
        if ($data['method'] === 'dropoff') $data['address'] = null;
        return $data;
    }

    private function assertReviewSession(IntakeSession $session, Request $request): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $state = app(IntakeSessionStateService::class);
        $state->reconcile($session);

        if ($session->status !== IntakeSession::STATUS_REVIEW) {
            if ($request->expectsJson()) {
                abort(422, 'Proses pemeriksaan ini sudah dilanjutkan. Buka Aktivitas atau detail barang untuk melihat tahap berikutnya.');
            }

            throw new HttpResponseException(
                redirect()->route('user.intake.review', $session)
                    ->with('info', 'Proses pemeriksaan ini sudah dilanjutkan. Lihat perkembangan barang dari halaman ini atau menu Aktivitas.')
            );
        }
        abort_unless($session->items()->whereNull('assessment_completed_at')->doesntExist(), 422, 'Masih ada barang yang belum selesai diperiksa.');

        foreach ($state->actionableItems($session) as $item) {
            abort_unless($item->asset && $item->asset->owner_user_id === $request->user()->id, 403);
            abort_unless($item->asset->preliminary_path, 422, 'Masih ada barang yang belum memiliki hasil cek kondisi.');
        }
    }
}
