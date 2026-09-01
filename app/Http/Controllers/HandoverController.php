<?php

namespace App\Http\Controllers;

use App\Models\{Asset, HandoverRequest, IssueReport, PartnerProfile};
use App\Services\{AssetEventService, AssetFlowService, IntakeSessionStateService, NotificationService, PartnerMatchingService, RegionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandoverController extends Controller
{
    public function matchForm(Asset $asset)
    {
        $this->own($asset);
        if ($redirect = $this->handoverPageRedirect($asset)) {
            return $redirect;
        }
        $this->assertCanCreateHandover($asset);

        return view('user.handovers.match', [
            'asset' => $asset,
            'districts' => app(RegionService::class)->surabayaDistricts(),
            'initialCapability' => app(AssetFlowService::class)->initialCapability($asset),
        ]);
    }

    public function resolveMapLink(Request $request)
    {
        $data = $request->validate([
            'url' => 'required|string|max:2000',
        ]);

        $maps = app(\App\Services\MapLinkService::class);
        $coordinates = $maps->resolve($data['url']);
        if (! $coordinates) {
            throw ValidationException::withMessages([
                'url' => 'Koordinat belum dapat dibaca dari link ini. Coba salin ulang link dari tombol Bagikan di Google Maps, atau pilih titik langsung pada peta.',
            ]);
        }

        $latitude = round((float) $coordinates['latitude'], 7);
        $longitude = round((float) $coordinates['longitude'], 7);

        return response()->json([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'maps_url' => $maps->canonicalUrl($latitude, $longitude),
            'resolved' => (bool) ($coordinates['resolved'] ?? false),
        ]);
    }

    public function match(Request $request, Asset $asset)
    {
        $this->own($asset);
        $this->assertCanCreateHandover($asset);

        $data = $this->validateRegion($this->validateHandover($request, false));
        $request->session()->put($this->matchSessionKey($asset), $data);

        $activeHelp = IssueReport::query()
            ->where('reporter_user_id', $request->user()->id)
            ->where('asset_id', $asset->id)
            ->where('category', 'matching_help')
            ->whereIn('status', ['open', 'in_review'])
            ->whereNotNull('context_json')
            ->latest()
            ->first();
        if ($activeHelp && ! $asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists()) {
            $authorizedAt = $activeHelp->context_json['authorized_at'] ?? null;
            $activeHelp->update(['context_json' => $data + ['authorized_at' => $authorizedAt]]);
        }

        // Hasil pencarian mempunyai URL GET sendiri agar refresh, validasi form, dan
        // tombol bantuan dapat kembali ke halaman ini tanpa mengenai route POST-only.
        return redirect()->route('user.handovers.partners', $asset);
    }

    public function partners(Request $request, Asset $asset)
    {
        $this->own($asset);
        if ($redirect = $this->handoverPageRedirect($asset)) {
            return $redirect;
        }
        $this->assertCanCreateHandover($asset);

        $data = $request->session()->get($this->matchSessionKey($asset));
        if (! is_array($data)) {
            return redirect()->route('user.handovers.match.form', $asset)
                ->with('info', 'Pilih tujuan dan cara penyerahan terlebih dahulu.');
        }

        $partners = app(PartnerMatchingService::class)->match(
            $asset,
            $data['method'],
            (float) $data['latitude'],
            (float) $data['longitude'],
            $data['handover_type'],
            $data['district']
        );

        $matchingHelpIssue = IssueReport::query()
            ->with('request.partner')
            ->where('reporter_user_id', $request->user()->id)
            ->where('asset_id', $asset->id)
            ->where('category', 'matching_help')
            ->whereIn('status', ['open', 'in_review'])
            ->whereNotNull('context_json')
            ->latest()
            ->first();

        return view('user.handovers.partners', [
            'asset' => $asset,
            'partners' => $partners,
            'handover' => $data,
            'initialCapability' => app(AssetFlowService::class)->initialCapability($asset),
            'matchingHelpIssue' => $matchingHelpIssue,
        ]);
    }

    public function create(Request $request, Asset $asset)
    {
        $this->own($asset);
        $this->assertCanCreateHandover($asset, false);

        $data = $this->validateRegion($this->validateHandover($request, true));
        $validPartnerIds = app(PartnerMatchingService::class)
            ->match($asset, $data['method'], (float) $data['latitude'], (float) $data['longitude'], $data['handover_type'], $data['district'])
            ->pluck('id');

        abort_unless(
            $validPartnerIds->contains((int) $data['partner_profile_id']),
            422,
            'Mitra tidak lagi sesuai dengan kategori atau layanan yang dibutuhkan, atau sedang tidak menerima permintaan baru.'
        );

        $handover = DB::transaction(function () use ($request, $asset, $data) {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->assertCanCreateHandover($locked, false);

            $partner = PartnerProfile::whereKey($data['partner_profile_id'])->lockForUpdate()->firstOrFail();
            abort_unless(
                app(PartnerMatchingService::class)->supportsExistingRequest($partner, $locked, $data['method']),
                422,
                'Mitra tidak lagi memenuhi layanan yang dibutuhkan barang ini.'
            );

            $distance = app(PartnerMatchingService::class)->haversine(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $partner->latitude,
                (float) $partner->longitude
            );

            $pickup = $data['method'] === 'pickup';
            $handover = HandoverRequest::create([
                'asset_id' => $locked->id,
                'user_id' => $request->user()->id,
                'partner_profile_id' => $partner->id,
                'method' => $data['method'],
                'handover_type' => $data['handover_type'],
                'ownership_acknowledged_at' => now(),
                'status' => 'pending',
                // Untuk drop-off titik ini hanya dipakai menghitung jarak/ranking dan tidak ditampilkan sebagai alamat rumah ke mitra.
                'pickup_latitude' => $data['latitude'],
                'pickup_longitude' => $data['longitude'],
                'pickup_address' => $pickup ? ($data['address'] ?? null) : null,
                'pickup_district' => $data['district'],
                'pickup_village' => $data['village'],
                'distance_km' => round($distance, 2),
                'within_radius' => ! $pickup || $distance <= (float) $partner->pickup_radius_km,
                'outside_radius' => $pickup && $distance > (float) $partner->pickup_radius_km,
                'requested_date' => $data['requested_date'] ?? null,
                'requested_time_start' => $data['time_start'] ?? null,
                'requested_time_end' => $data['time_end'] ?? null,
                'schedule_status' => 'requested',
            ]);

            // Asset menyimpan tujuan aktif untuk flow saat ini; request menyimpan snapshot audit-nya.
            $locked->update([
                'status' => 'requested',
                'handover_type' => $data['handover_type'],
            ]);

            return $handover;
        });

        app(AssetEventService::class)->add(
            $asset,
            'HANDOVER_REQUESTED',
            'Permintaan penyerahan dibuat',
            'Menunggu respons mitra.',
            ['method' => $data['method'], 'handover_type' => $data['handover_type']]
        );
        app(IntakeSessionStateService::class)->reconcileForAsset($asset);
        app(NotificationService::class)->send(
            $handover->partner->user,
            'Permintaan baru SIRKEL',
            'Ada barang '.$asset->passport_code.' yang menunggu respons.',
            route('partner.requests.show', $handover)
        );
        $request->session()->forget($this->matchSessionKey($asset));
        IssueReport::query()
            ->where('reporter_user_id', $request->user()->id)
            ->where('asset_id', $asset->id)
            ->where('category', 'matching_help')
            ->whereIn('status', ['open', 'in_review'])
            ->update([
                'status' => 'resolved',
                'resolved_by' => null,
                'resolved_at' => now(),
            ]);

        return redirect()->route('user.assets.show', $asset)->with('success', 'Permintaan dikirim ke mitra.');
    }

    public function acceptSchedule(HandoverRequest $handover)
    {
        abort_unless($handover->user_id === auth()->id(), 403);

        DB::transaction(function () use ($handover) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === auth()->id(), 403);
            abort_if(in_array($locked->status, HandoverRequest::TERMINAL_STATUSES, true), 422, 'Penyerahan ini sudah selesai atau ditutup.');
            abort_if($locked->asset->core_locked_at, 422, 'Barang sudah diterima mitra; jadwal penyerahan tidak dapat diubah lagi.');
            abort_unless($locked->readyForPhysicalHandover(), 422, 'Selesaikan tahap kesepakatan terlebih dahulu sebelum menerima jadwal.');
            abort_unless($locked->schedule_status === 'proposed' && $locked->partner_proposed_time, 422, 'Belum ada usulan jadwal baru dari mitra.');
            abort_if($locked->partner_proposed_time->isPast(), 422, 'Usulan jadwal sudah lewat. Minta mitra mengirim jadwal baru.');

            $locked->update([
                'schedule_status' => 'accepted',
                'requested_date' => $locked->partner_proposed_time->toDateString(),
                'requested_time_start' => $locked->partner_proposed_time->format('H:i'),
            ]);

            app(AssetEventService::class)->add(
                $locked->asset,
                'SCHEDULE_ACCEPTED',
                'Usulan jadwal diterima',
                'Warga menyetujui usulan jadwal mitra.'
            );
        });

        return back()->with('success', 'Jadwal usulan mitra diterima.');
    }

    public function afterOfferRejection(Request $request, HandoverRequest $handover)
    {
        abort_unless($handover->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'action' => 'required|in:reoffer,change_partner,cancel',
        ]);

        $redirect = null;
        DB::transaction(function () use ($request, $handover, $data, &$redirect) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $request->user()->id, 403);
            abort_unless($locked->status === 'offer_rejected', 422, 'Penawaran ini sudah tidak berada pada tahap memilih tindak lanjut.');
            $asset = Asset::whereKey($locked->asset_id)->lockForUpdate()->firstOrFail();
            abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra dan tidak dapat mengulang negosiasi awal.');

            if ($data['action'] === 'reoffer') {
                $locked->update(['status' => 'accepted']);
                $asset->update(['status' => 'partner_accepted']);
                app(AssetEventService::class)->add($asset, 'REOFFER_REQUESTED', 'Warga meminta penawaran baru', 'Mitra yang sama diminta mengirim versi penawaran berikutnya. Data penyerahan dan jadwal tetap dipertahankan.');
                app(NotificationService::class)->send(
                    $locked->partner->user,
                    'Warga meminta penawaran baru',
                    'Penawaran sebelumnya untuk '.$asset->passport_code.' ditolak. Warga memilih tetap dengan mitra Anda dan meminta penawaran baru.',
                    route('partner.requests.show', $locked)
                );
                $redirect = redirect()->route('user.assets.show', $asset)->with('success', 'Mitra yang sama sudah diminta memberikan penawaran baru. Data penyerahan tetap tersimpan.');
                return;
            }

            if ($data['action'] === 'change_partner') {
                $matchData = [
                    'method' => $locked->method,
                    'handover_type' => $locked->effectiveHandoverType(),
                    'latitude' => (float) $locked->pickup_latitude,
                    'longitude' => (float) $locked->pickup_longitude,
                    'address' => $locked->pickup_address,
                    'district' => $locked->pickup_district,
                    'village' => $locked->pickup_village,
                    'requested_date' => $locked->requested_date?->toDateString(),
                    'time_start' => $locked->requested_time_start,
                    'time_end' => $locked->requested_time_end,
                ];
                $request->session()->put($this->matchSessionKey($asset), $matchData);
                $asset->update(['status' => 'matching']);
                app(AssetEventService::class)->add($asset, 'PARTNER_CHANGE_REQUESTED', 'Warga memilih mencari mitra lain', 'Data cara penyerahan sebelumnya dipertahankan sehingga warga tidak perlu mengisi ulang.');
                $redirect = redirect()->route('user.handovers.partners', $asset)->with('info', 'Data penyerahan sebelumnya tetap dipakai. Pilih mitra baru yang sesuai.');
                return;
            }

            $locked->update(['status' => 'cancelled_by_user', 'cancel_reason' => 'Dibatalkan warga setelah menolak penawaran.']);
            $asset->update(['status' => 'matching']);
            app(AssetEventService::class)->add($asset, 'REQUEST_CANCELLED', 'Penyerahan dibatalkan warga', 'Warga membatalkan penyerahan setelah menolak penawaran.');
            app(NotificationService::class)->send(
                $locked->partner->user,
                'Penyerahan dibatalkan warga',
                'Warga membatalkan permintaan '.$asset->passport_code.' setelah menolak penawaran.',
                route('partner.requests.show', $locked)
            );
            $redirect = redirect()->route('user.assets.show', $asset)->with('success', 'Penyerahan dibatalkan. Riwayat penawaran tetap tersimpan.');
        });

        return $redirect;
    }

    public function cancel(Request $request, HandoverRequest $handover)
    {
        abort_unless($handover->user_id === auth()->id(), 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        DB::transaction(function () use ($handover, $data) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === auth()->id(), 403);
            $asset = Asset::whereKey($locked->asset_id)->lockForUpdate()->firstOrFail();
            abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra. Barang sudah diterima mitra dan tidak dapat dibatalkan seperti permintaan biasa. Gunakan pengalihan layanan atau laporkan masalah bila diperlukan.');
            abort_if(in_array($locked->status, HandoverRequest::TERMINAL_STATUSES, true), 422, 'Permintaan ini sudah selesai.');

            $locked->update(['status' => 'cancelled_by_user', 'cancel_reason' => $data['reason']]);
            $asset->update(['status' => 'matching']);
            app(AssetEventService::class)->add($asset, 'REQUEST_CANCELLED', 'Permintaan dibatalkan warga', $data['reason']);

            IssueReport::query()
                ->where('category', 'matching_help')
                ->where('handover_request_id', $locked->id)
                ->update([
                    'status' => 'open',
                    'resolved_by' => null,
                    'resolved_at' => null,
                ]);
        });

        app(NotificationService::class)->send(
            $handover->partner->user,
            'Permintaan dibatalkan warga',
            'Permintaan untuk '.$handover->asset->passport_code.' dibatalkan.'
        );

        return back()->with('success', 'Permintaan dibatalkan tanpa menghapus riwayat.');
    }

    private function validateHandover(Request $request, bool $withPartner): array
    {
        $rules = [
            'method' => 'required|in:pickup,dropoff',
            'handover_type' => 'required|in:sale,free_handover,donation',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|required_if:method,pickup|string|max:500',
            'district' => 'required|string|max:100',
            'village' => 'required|string|max:100',
            'requested_date' => 'required|date|after_or_equal:today|before_or_equal:'.now()->endOfYear()->toDateString(),
            'time_start' => ['nullable', 'required_if:method,pickup', 'date_format:H:i', 'regex:/^(?:[01]\d|2[0-3]):(?:00|30)$/'],
            'time_end' => ['nullable', 'required_if:method,pickup', 'date_format:H:i', 'regex:/^(?:[01]\d|2[0-3]):(?:00|30)$/', 'after:time_start'],
        ];

        if ($withPartner) {
            $rules['partner_profile_id'] = 'required|exists:partner_profiles,id';
            // Persetujuan penyerahan final baru wajib saat warga benar-benar memilih mitra.
            // Tahap pencarian mitra hanya menyaring kandidat dan tidak boleh terblokir oleh persetujuan final.
            $rules['ownership_acknowledgement'] = 'required|accepted';
        }

        return $request->validate($rules);
    }

    private function validateRegion(array $data): array
    {
        $regions = app(RegionService::class);
        if (! $regions->isValidSurabayaLocation($data['district'], $data['village'])) {
            throw ValidationException::withMessages([
                'village' => 'Kelurahan tidak sesuai dengan kecamatan yang dipilih. Pilih kembali dari daftar.',
            ]);
        }

        $normalized = $regions->normalizeLocation($data['district'], $data['village']);
        $data['district'] = $normalized['district'];
        $data['village'] = $normalized['village'];
        if (($data['method'] ?? null) === 'dropoff') {
            $data['address'] = null;
        }

        return $data;
    }

    private function handoverPageRedirect(Asset $asset)
    {
        if ($asset->final_path) {
            return redirect()->route('user.assets.show', $asset)
                ->with('info', 'Penanganan barang ini sudah selesai.');
        }

        if ($asset->core_locked_at) {
            return redirect()->route('user.assets.show', $asset)
                ->with('info', 'Barang sudah diterima mitra dan sedang berada dalam proses penanganan.');
        }

        if ($asset->status === 'offer_rejected') {
            return redirect()->route('user.assets.show', $asset)
                ->with('info', 'Pilih dulu apakah ingin meminta penawaran baru, mengganti mitra, atau membatalkan penyerahan.');
        }

        if ($asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists()) {
            return redirect()->route('user.assets.show', $asset)
                ->with('info', 'Barang ini sudah memiliki penyerahan yang sedang berjalan.');
        }

        if (! $asset->preliminary_path) {
            return redirect()->route('user.assets.assessment', $asset)
                ->with('info', 'Lakukan cek kondisi terlebih dahulu sebelum memilih penyerahan.');
        }

        return null;
    }

    private function assertCanCreateHandover(Asset $asset, bool $checkActive = true): void
    {
        abort_if($asset->final_path, 422, 'Barang sudah memiliki hasil akhir.');
        abort_unless($asset->preliminary_path, 422, 'Lakukan cek kondisi terlebih dahulu sebelum memilih penyerahan.');
        abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra dan tidak dapat membuat penyerahan warga baru.');
        abort_if($asset->status === 'offer_rejected', 422, 'Tentukan tindak lanjut penawaran yang ditolak terlebih dahulu.');
        if ($checkActive) {
            abort_if(
                $asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists(),
                422,
                'Barang ini masih memiliki permintaan penyerahan aktif.'
            );
        } else {
            abort_if(
                HandoverRequest::where('asset_id', $asset->id)->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists(),
                422,
                'Barang ini masih memiliki permintaan penyerahan aktif.'
            );
        }
    }

    private function matchSessionKey(Asset $asset): string
    {
        return 'handover_match.'.$asset->id;
    }

    private function own(Asset $asset): void
    {
        abort_unless($asset->owner_user_id === auth()->id(), 403);
    }
}
