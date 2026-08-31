<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\{Asset, AssetCustody, HandoverRequest, IssueReport, Offer, PartnerTransfer, User};
use App\Services\{AssetEventService, NotificationService, OfferLifecycleService, PartnerMatchingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerRequestController extends Controller
{
    public function index(Request $request)
    {
        app(OfferLifecycleService::class)->expireOverdue();
        $profile = $this->profile(true);
        $scope = (string) $request->query('scope', 'active');
        if (!in_array($scope, ['active', 'history'], true)) {
            $scope = 'active';
        }

        $query = HandoverRequest::with(['asset.category', 'asset.custody', 'user', 'currentOffer'])
            ->where('partner_profile_id', $profile->id);

        if ($scope === 'active') {
            $query->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES);
        } else {
            $query->whereIn('status', HandoverRequest::TERMINAL_STATUSES);
        }

        $transferQuery = PartnerTransfer::with([
            'asset.category',
            'asset.photos',
            'asset.assessments',
            'fromPartner',
            'toPartner',
        ])->where('to_partner_id', $profile->id);

        if ($scope === 'active') {
            $transferQuery->where('status', 'pending');
        } else {
            $transferQuery->whereIn('status', ['received', 'declined', 'cancelled']);
        }

        return view('partner.requests.index', [
            'requests' => $query->latest()->paginate(15)->withQueryString(),
            'incomingTransfers' => $transferQuery->latest('requested_at')->get(),
            'scope' => $scope,
            'profile' => $profile,
        ]);
    }

    public function show(HandoverRequest $handover)
    {
        $this->can($handover);
        app(OfferLifecycleService::class)->refresh($handover);
        $handover->load([
            'asset.category',
            'asset.photos',
            'asset.assessments',
            'asset.events',
            'asset.custody',
            'user',
            'offers',
        ]);

        $matchingHelpIssue = IssueReport::query()
            ->where('category', 'matching_help')
            ->where('handover_request_id', $handover->id)
            ->latest()
            ->first();

        return view('partner.requests.show', compact('handover', 'matchingHelpIssue'));
    }

    public function accept(HandoverRequest $handover)
    {
        $this->can($handover);
        $profile = $this->profile(true);

        DB::transaction(function () use ($handover, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            abort_unless($locked->status === 'pending', 422, 'Permintaan ini sudah diproses.');
            $matchingHelpIssue = IssueReport::query()
                ->where('category', 'matching_help')
                ->where('handover_request_id', $locked->id)
                ->lockForUpdate()
                ->first();
            $supported = $matchingHelpIssue
                ? app(PartnerMatchingService::class)->supportsAssistedRequest($profile, $locked->asset, $locked->method)
                : app(PartnerMatchingService::class)->supportsExistingRequest($profile, $locked->asset, $locked->method);
            abort_unless(
                $supported,
                422,
                'Layanan pada profil mitra Anda sudah tidak sesuai dengan kebutuhan barang ini. Periksa profil layanan sebelum menerima permintaan.'
            );

            $scheduleStatus = $locked->schedule_status;
            if ($locked->effectiveHandoverType() !== 'sale' && $locked->requested_date) {
                $scheduleStatus = 'accepted';
            }

            $locked->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'schedule_status' => $scheduleStatus,
            ]);
            $locked->asset->update(['status' => 'partner_accepted']);

            app(AssetEventService::class)->add(
                $locked->asset,
                'PARTNER_ACCEPTED',
                'Mitra menerima permintaan',
                $locked->method === 'pickup'
                ? 'Kontak dan titik penjemputan sekarang dapat digunakan oleh mitra terpilih.'
                : 'Kontak warga sekarang dapat digunakan. Warga akan mengantar barang ke lokasi mitra.'
            );

            if ($matchingHelpIssue) {
                $matchingHelpIssue->update([
                    'status' => 'resolved',
                    'resolved_by' => null,
                    'resolved_at' => now(),
                ]);
            }
        });

        app(NotificationService::class)->send(
            $handover->user,
            'Mitra menerima permintaan',
            'Mitra ' . $handover->partner->business_name . ' menerima permintaan untuk ' . $handover->asset->passport_code,
            route('user.assets.show', $handover->asset)
        );

        return back()->with('success', $handover->effectiveHandoverType() === 'sale'
            ? 'Permintaan diterima. Buat penawaran nilai sebelum barang diserahkan.'
            : 'Permintaan diterima. Siapkan jadwal penyerahan barang.');
    }

    public function decline(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $profile = $this->profile(true);

        DB::transaction(function () use ($handover, $data, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            abort_unless($locked->status === 'pending', 422, 'Permintaan ini sudah diproses.');
            $asset = Asset::whereKey($locked->asset_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'status' => 'declined',
                'declined_at' => now(),
                'decline_reason' => $data['reason'],
            ]);
            $asset->update(['status' => 'matching']);
            app(AssetEventService::class)->add($asset, 'PARTNER_DECLINED', 'Mitra menolak permintaan', $data['reason']);

            IssueReport::query()
                ->where('category', 'matching_help')
                ->where('handover_request_id', $locked->id)
                ->whereIn('status', ['open', 'in_review'])
                ->update([
                    'status' => 'open',
                    'resolved_by' => null,
                    'resolved_at' => null,
                ]);
        });

        $matchingHelpIssue = IssueReport::query()
            ->where('category', 'matching_help')
            ->where('handover_request_id', $handover->id)
            ->latest()
            ->first();

        app(NotificationService::class)->send(
            $handover->user,
            'Permintaan belum dapat diterima',
            $matchingHelpIssue
            ? 'Mitra yang dihubungi belum dapat menerima barang ini. SIRKEL akan melanjutkan pencarian mitra.'
            : 'Mitra belum dapat menerima permintaan. Anda dapat memilih mitra lain.',
            $matchingHelpIssue
            ? route('user.assets.show', $handover->asset)
            : route('user.handovers.match.form', $handover->asset)
        );

        if ($matchingHelpIssue) {
            User::query()->where('role', UserRole::ADMIN->value)->get()->each(function (User $admin) use ($handover) {
                app(NotificationService::class)->send(
                    $admin,
                    'Mitra belum dapat menerima bantuan',
                    $handover->partner->business_name . ' belum dapat menerima ' . $handover->asset->passport_code . '. Pilih mitra lain untuk melanjutkan bantuan.',
                    route('admin.issues.index'),
                    false
                );
            });
        }

        return redirect()->route('partner.requests.index')->with('success', $matchingHelpIssue
            ? 'Permintaan ditolak. SIRKEL akan melanjutkan pencarian mitra untuk warga.'
            : 'Permintaan ditolak. Warga dapat memilih mitra lain.');
    }

    public function offer(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        app(OfferLifecycleService::class)->refresh($handover);
        $profile = $this->profile(true);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:999999999',
            'note' => 'nullable|string|max:800',
            'valid_hours' => 'required|integer|min:1|max:168',
        ]);
        $validHours = (int) $data['valid_hours'];

        $offer = DB::transaction(function () use ($handover, $data, $validHours, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            abort_unless($locked->effectiveHandoverType() === 'sale', 422, 'Penawaran nilai hanya digunakan jika warga memilih penyerahan dengan penawaran nilai.');
            abort_unless(in_array($locked->status, ['accepted', 'offered'], true), 422, 'Penawaran hanya dapat dibuat setelah permintaan diterima dan sebelum warga menyetujui penawaran.');
            abort_if($locked->asset->core_locked_at, 422, 'Barang sudah diterima. Penawaran awal tidak dapat diubah lagi.');

            $locked->offers()->where('is_current', true)->where('status', 'waiting_user')->update([
                'is_current' => false,
                'status' => 'superseded',
            ]);
            $version = ((int) $locked->offers()->max('version')) + 1;

            $offer = Offer::create([
                'handover_request_id' => $locked->id,
                'version' => $version,
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
                'offered_at' => now(),
                'expires_at' => now()->addHours($validHours),
                'is_current' => true,
                'status' => 'waiting_user',
            ]);

            $locked->update(['status' => 'offered']);
            $locked->asset->update(['status' => 'offered']);
            app(AssetEventService::class)->add(
                $locked->asset,
                'OFFER_CREATED',
                'Penawaran dibuat',
                'Penawaran versi ' . $offer->version . ' berlaku sampai ' . $offer->expires_at->format('d M Y H:i')
            );

            return $offer;
        });

        app(NotificationService::class)->send(
            $handover->user,
            'Penawaran baru dari mitra',
            'Ada penawaran untuk ' . $handover->asset->passport_code . '.',
            route('user.assets.show', $handover->asset)
        );

        return back()->with('success', 'Penawaran dikirim dan berlaku sampai ' . $offer->expires_at->format('d M Y H:i') . '.');
    }

    public function proposeSchedule(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        $profile = $this->profile(true);
        $data = $request->validate(['proposed_time' => 'required|date|after:now']);

        DB::transaction(function () use ($handover, $data, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            abort_if(in_array($locked->status, HandoverRequest::TERMINAL_STATUSES, true), 422, 'Penyerahan ini sudah selesai atau ditutup.');
            abort_if($locked->asset->core_locked_at, 422, 'Barang sudah diterima; jadwal penyerahan tidak dapat diubah lagi.');
            abort_unless($locked->readyForPhysicalHandover(), 422, $locked->effectiveHandoverType() === 'sale'
                ? 'Warga harus menerima penawaran nilai terlebih dahulu sebelum jadwal baru disepakati.'
                : 'Terima permintaan terlebih dahulu sebelum mengusulkan jadwal.');

            $locked->update([
                'partner_proposed_time' => $data['proposed_time'],
                'schedule_status' => 'proposed',
            ]);
            app(AssetEventService::class)->add($locked->asset, 'SCHEDULE_PROPOSED', 'Mitra mengusulkan jadwal baru', $data['proposed_time']);
        });

        app(NotificationService::class)->send(
            $handover->user,
            'Usulan jadwal baru',
            'Mitra mengusulkan jadwal baru untuk ' . $handover->asset->passport_code,
            route('user.assets.show', $handover->asset)
        );

        return back()->with('success', 'Usulan jadwal dikirim. Tunggu warga menyetujuinya.');
    }

    public function receive(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        $profile = $this->profile(true);
        $data = $request->validate(['verified_weight_kg' => 'required|numeric|min:0.001|max:9999']);

        DB::transaction(function () use ($request, $handover, $data, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            $asset = Asset::whereKey($locked->asset_id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->readyForPhysicalHandover(), 422, $locked->effectiveHandoverType() === 'sale'
                ? 'Warga perlu menerima penawaran sebelum barang dapat dicatat diterima.'
                : 'Tahap penyerahan belum siap untuk konfirmasi penerimaan barang.');
            abort_if($locked->schedule_status === 'proposed', 422, 'Usulan jadwal baru masih menunggu persetujuan warga.');
            abort_if(
                $locked->requested_date && $locked->schedule_status !== 'accepted',
                422,
                'Jadwal penyerahan belum disepakati. Selesaikan jadwal sebelum mencatat barang diterima.'
            );
            abort_if($asset->core_locked_at || $asset->custody()->whereNull('released_at')->exists(), 422, 'Barang ini sudah tercatat diterima oleh mitra.');

            $asset->update([
                'core_locked_at' => now(),
                'verified_weight_kg' => $data['verified_weight_kg'],
                'status' => 'received',
            ]);

            AssetCustody::create([
                'asset_id' => $asset->id,
                'partner_profile_id' => $profile->id,
                'received_by_user_id' => $request->user()->id,
                'received_at' => now(),
            ]);

            // completed = penyerahan warga → mitra selesai; bukan hasil akhir barang.
            $locked->update(['status' => 'completed']);
            app(AssetEventService::class)->add(
                $asset,
                'RECEIVED',
                'Barang diterima mitra',
                'Berat terverifikasi: ' . $data['verified_weight_kg'] . ' kg. Penyerahan selesai; pemeriksaan mitra menjadi tahap berikutnya.'
            );
        });

        app(NotificationService::class)->send(
            $handover->user,
            'Barang diterima mitra',
            'Barang ' . $handover->asset->passport_code . ' telah diterima dan ditimbang. Selanjutnya mitra melakukan pemeriksaan.',
            route('user.assets.show', $handover->asset)
        );

        return redirect()->route('partner.assets.show', $handover->asset)
            ->with('success', 'Penyerahan fisik selesai. Sekarang periksa barang dan tentukan hasil atau layanan lanjutan.');
    }

    public function assess(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        if ($request->has('final_path') && !$request->has('handling_decision')) {
            $request->merge(['handling_decision' => $request->input('final_path')]);
        }

        return app(PartnerAssetController::class)->assess($request, $handover->asset);
    }

    public function cancel(Request $request, HandoverRequest $handover)
    {
        $this->can($handover);
        $profile = $this->profile(true);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        DB::transaction(function () use ($handover, $data, $profile) {
            $locked = HandoverRequest::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->partner_profile_id === $profile->id, 403);
            $asset = Asset::whereKey($locked->asset_id)->lockForUpdate()->firstOrFail();
            abort_if($asset->core_locked_at, 422, 'Barang sudah diterima mitra dan masuk proses penanganan SIRKEL. Gunakan pengalihan atau laporan masalah bila diperlukan; penyerahan tidak dapat dibatalkan setelah penerimaan fisik.');
            abort_if(in_array($locked->status, HandoverRequest::TERMINAL_STATUSES, true), 422, 'Permintaan ini sudah selesai.');

            $locked->update(['status' => 'cancelled_by_partner', 'cancel_reason' => $data['reason']]);
            $asset->update(['status' => 'matching']);
            app(AssetEventService::class)->add($asset, 'REQUEST_CANCELLED', 'Permintaan dibatalkan mitra', $data['reason']);

            IssueReport::query()
                ->where('category', 'matching_help')
                ->where('handover_request_id', $locked->id)
                ->update([
                    'status' => 'open',
                    'resolved_by' => null,
                    'resolved_at' => null,
                ]);
        });

        $matchingHelpIssue = IssueReport::query()
            ->where('category', 'matching_help')
            ->where('handover_request_id', $handover->id)
            ->latest()
            ->first();

        app(NotificationService::class)->send(
            $handover->user,
            'Permintaan dibatalkan mitra',
            $matchingHelpIssue
            ? 'Mitra membatalkan permintaan untuk ' . $handover->asset->passport_code . '. SIRKEL akan melanjutkan pencarian mitra.'
            : 'Mitra membatalkan permintaan untuk ' . $handover->asset->passport_code . '. Anda dapat memilih mitra lain.',
            $matchingHelpIssue
            ? route('user.assets.show', $handover->asset)
            : route('user.handovers.match.form', $handover->asset)
        );

        if ($matchingHelpIssue) {
            User::query()->where('role', UserRole::ADMIN->value)->get()->each(function (User $admin) use ($handover) {
                app(NotificationService::class)->send(
                    $admin,
                    'Mitra membatalkan bantuan pencarian',
                    $handover->partner->business_name . ' membatalkan permintaan ' . $handover->asset->passport_code . '. Pilih mitra lain untuk melanjutkan bantuan.',
                    route('admin.issues.index'),
                    false
                );
            });
        }

        return redirect()->route('partner.requests.index')->with('success', $matchingHelpIssue
            ? 'Pembatalan tercatat. SIRKEL akan melanjutkan pencarian mitra untuk warga.'
            : 'Pembatalan tercatat. Warga dapat memilih mitra lain.');
    }

    private function profile(bool $approved = false)
    {
        $profile = auth()->user()->partnerProfile;
        abort_unless($profile, 403);
        if ($approved) {
            abort_unless($profile->verification_status === 'approved', 403, 'Mitra belum terverifikasi.');
        }
        return $profile;
    }

    private function can(HandoverRequest $handover): void
    {
        $profile = $this->profile(true);
        abort_unless($handover->partner_profile_id === $profile->id, 403);
    }
}
