<?php

namespace App\Http\Controllers;

use App\Models\{Asset, AssetCustody, AssetAssessment, PartnerProfile, PartnerTransfer};
use App\Services\{AssetEventService, AssetFlowService, NotificationService, PartnerMatchingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function show(PartnerTransfer $transfer)
    {
        $target = $this->profile();
        abort_unless($transfer->to_partner_id === $target->id, 403);

        $transfer->load([
            'asset.category.group',
            'asset.photos',
            'asset.assessments.assessor',
            'fromPartner.user',
            'toPartner.user',
        ]);

        $sourceAssessment = $transfer->asset->assessments
            ->where('assessment_type', 'partner')
            ->where('assessor_user_id', $transfer->fromPartner?->user_id)
            ->sortByDesc('id')
            ->first();

        return view('partner.transfers.show', [
            'transfer' => $transfer,
            'asset' => $transfer->asset,
            'sourceAssessment' => $sourceAssessment,
        ]);
    }

    public function create(Asset $asset)
    {
        $source = $this->profile();
        $this->assertSourceOwnsAsset($asset, $source);

        $assessment = $this->latestAssessmentByPartner($asset, $source);
        abort_unless($assessment && app(AssetFlowService::class)->isTransferDecision($assessment->result_path), 422, 'Simpan pemeriksaan dan pilih layanan lanjutan terlebih dahulu.');

        $flow = app(AssetFlowService::class);
        $requiredCapability = $flow->requiredTransferCapability($asset, $assessment, $source);
        if ($conflict = $flow->transferCapabilityConflict($asset, $requiredCapability)) {
            return redirect()->route('partner.assets.show', $asset)->withErrors(['flow' => $conflict]);
        }
        $partners = app(PartnerMatchingService::class)->rankTransferTargets(
            $this->eligibleTargets($asset, $source, $requiredCapability)->get(),
            $source,
            $requiredCapability,
            $asset
        );

        return view('partner.transfers.create', [
            'asset' => $asset,
            'partners' => $partners,
            'requiredCapability' => $requiredCapability,
            'requiredCapabilityLabel' => \App\Support\SirkelUi::label($requiredCapability),
            'assessment' => $assessment,
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $source = $this->profile();
        $data = $request->validate([
            'to_partner_id' => 'required|exists:partner_profiles,id',
            'note' => 'required|string|max:700',
        ]);
        abort_if((int) $data['to_partner_id'] === $source->id, 422, 'Mitra tujuan harus berbeda.');

        $transfer = DB::transaction(function () use ($request, $asset, $source, $data) {
            $lockedAsset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->assertSourceOwnsAsset($lockedAsset, $source);
            abort_if(PartnerTransfer::where('asset_id', $lockedAsset->id)->where('status', 'pending')->exists(), 422, 'Masih ada pengalihan yang sedang berjalan.');

            $assessment = $this->latestAssessmentByPartner($lockedAsset, $source);
            abort_unless($assessment && app(AssetFlowService::class)->isTransferDecision($assessment->result_path), 422, 'Pemeriksaan terakhir belum menentukan layanan lanjutan.');
            $flow = app(AssetFlowService::class);
            $requiredCapability = $flow->requiredTransferCapability($lockedAsset, $assessment, $source);
            if ($conflict = $flow->transferCapabilityConflict($lockedAsset, $requiredCapability)) {
                throw ValidationException::withMessages(['to_partner_id' => $conflict]);
            }

            $target = $this->eligibleTargets($lockedAsset, $source, $requiredCapability)
                ->whereKey($data['to_partner_id'])
                ->lockForUpdate()
                ->first();
            abort_unless($target, 422, 'Mitra tujuan tidak lagi memiliki layanan ' . \App\Support\SirkelUi::label($requiredCapability) . ' yang dibutuhkan barang ini. Pilih mitra lain.');

            $transfer = PartnerTransfer::create([
                'asset_id' => $lockedAsset->id,
                'from_partner_id' => $source->id,
                'to_partner_id' => $target->id,
                'requested_by_user_id' => $request->user()->id,
                'required_capability' => $requiredCapability,
                'status' => 'pending',
                'note' => $data['note'],
                'requested_at' => now(),
            ]);

            // Belum berpindah custody. Status ini hanya menandai permintaan transfer sedang menunggu target.
            $lockedAsset->update(['status' => 'transfer_pending']);
            app(AssetEventService::class)->add(
                $lockedAsset,
                'TRANSFER_REQUESTED',
                'Pengalihan ke mitra lanjutan diajukan',
                'Menunggu konfirmasi ' . $target->business_name . ' untuk layanan ' . \App\Support\SirkelUi::label($requiredCapability) . '. ' . $data['note'],
                [
                    'to_partner' => $target->business_name,
                    'target_capability' => $requiredCapability,
                ]
            );

            return $transfer->load(['toPartner.user', 'asset.owner']);
        });

        app(NotificationService::class)->send(
            $transfer->toPartner->user,
            'Pengalihan barang menunggu respons',
            'Ada barang ' . $transfer->asset->passport_code . ' yang memerlukan layanan ' . \App\Support\SirkelUi::label($transfer->required_capability) . '. Terima hanya jika barang benar-benar dapat Anda tangani.',
            route('partner.transfers.show', $transfer)
        );
        app(NotificationService::class)->send(
            $transfer->asset->owner,
            'Mitra mengajukan pengalihan barang',
            'Barang ' . $transfer->asset->passport_code . ' akan dialihkan ke layanan ' . \App\Support\SirkelUi::label($transfer->required_capability) . '. Tanggung jawab belum berpindah sampai mitra tujuan menerima.',
            route('user.assets.show', $transfer->asset)
        );

        return redirect()->route('partner.assets.show', $transfer->asset)
            ->with('success', 'Pengalihan diajukan. Barang tetap menjadi tanggung jawab Anda sampai mitra tujuan mengonfirmasi penerimaan.');
    }

    public function receive(PartnerTransfer $transfer)
    {
        $target = $this->profile();

        $asset = DB::transaction(function () use ($transfer, $target) {
            $lockedTransfer = PartnerTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedTransfer->to_partner_id === $target->id, 403);
            abort_unless($lockedTransfer->status === 'pending', 422, 'Pengalihan ini sudah diproses.');

            $lockedTarget = PartnerProfile::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (!$lockedTarget->canReceiveNewRequests()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Profil mitra sedang tidak menerima pekerjaan baru. Aktifkan penerimaan permintaan terlebih dahulu, atau tolak pengalihan agar mitra asal dapat memilih tujuan lain.',
                ]);
            }

            $asset = Asset::whereKey($lockedTransfer->asset_id)->lockForUpdate()->firstOrFail();
            abort_if($asset->final_path, 422, 'Barang ini sudah memiliki hasil akhir.');

            $requiredCapability = $lockedTransfer->required_capability;
            if (!$requiredCapability) {
                $source = $lockedTransfer->fromPartner;
                $assessment = $this->latestAssessmentByPartner($asset, $source);
                $requiredCapability = app(AssetFlowService::class)->requiredTransferCapability($asset, $assessment, $source);
            }

            if ($conflict = app(AssetFlowService::class)->transferCapabilityConflict($asset, $requiredCapability)) {
                throw ValidationException::withMessages(['flow' => $conflict]);
            }
            $previousPartnerId = $this->previousPartnerId($asset, (int) $lockedTransfer->from_partner_id);
            if ($previousPartnerId && $previousPartnerId === $lockedTarget->id) {
                throw ValidationException::withMessages([
                    'flow' => 'Pengalihan ini akan langsung mengembalikan barang ke mitra yang baru saja menyerahkannya. SIRKEL memblokir alur bolak-balik. Mitra asal harus meninjau ulang hasil pemeriksaan atau memilih mitra lain yang sesuai.',
                ]);
            }

            abort_unless($lockedTarget->hasApprovedCapability($requiredCapability), 422, 'Layanan ' . \App\Support\SirkelUi::label($requiredCapability) . ' tidak lagi aktif pada profil mitra Anda. Tolak pengalihan ini atau hubungi admin.');
            abort_unless(app(PartnerMatchingService::class)->supportsCategory($lockedTarget, $asset), 422, 'Kategori atau kelompok barang ini tidak lagi termasuk cakupan yang Anda terima.');

            $sourceCustody = AssetCustody::where('asset_id', $asset->id)
                ->where('partner_profile_id', $lockedTransfer->from_partner_id)
                ->whereNull('released_at')
                ->lockForUpdate()
                ->first();
            abort_unless($sourceCustody, 422, 'Tanggung jawab barang di mitra asal sudah berubah. Muat ulang halaman.');
            abort_if(
                AssetCustody::where('asset_id', $asset->id)->whereNull('released_at')->where('partner_profile_id', '!=', $lockedTransfer->from_partner_id)->exists(),
                422,
                'Barang sudah tercatat berada pada mitra lain.'
            );

            $sourceCustody->update(['released_at' => now()]);
            AssetCustody::create([
                'asset_id' => $asset->id,
                'partner_profile_id' => $lockedTarget->id,
                'received_by_user_id' => auth()->id(),
                'received_at' => now(),
            ]);

            $lockedTransfer->update([
                'status' => 'received',
                'received_at' => now(),
                'received_by_user_id' => auth()->id(),
                'required_capability' => $requiredCapability,
            ]);
            $asset->update(['status' => 'received']);

            app(AssetEventService::class)->add(
                $asset,
                'TRANSFER_RECEIVED',
                'Barang diterima mitra lanjutan',
                'Tanggung jawab barang berpindah setelah mitra tujuan mengonfirmasi penerimaan untuk layanan ' . \App\Support\SirkelUi::label($requiredCapability) . '.',
                ['partner' => $lockedTarget->business_name, 'target_capability' => $requiredCapability]
            );

            return $asset;
        });

        app(NotificationService::class)->send(
            $asset->owner,
            'Transfer barang diterima',
            'Barang ' . $asset->passport_code . ' telah diterima mitra lanjutan dan akan menjalani pemeriksaan berikutnya.',
            route('user.assets.show', $asset)
        );

        return redirect()->route('partner.assets.show', $asset)
            ->with('success', 'Barang diterima. Sekarang lakukan pemeriksaan sesuai layanan mitra Anda.');
    }

    public function decline(Request $request, PartnerTransfer $transfer)
    {
        $target = $this->profile();
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $asset = DB::transaction(function () use ($transfer, $target, $data) {
            $lockedTransfer = PartnerTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedTransfer->to_partner_id === $target->id, 403);
            abort_unless($lockedTransfer->status === 'pending', 422, 'Pengalihan ini sudah diproses.');
            $asset = Asset::whereKey($lockedTransfer->asset_id)->lockForUpdate()->firstOrFail();

            $lockedTransfer->update([
                'status' => 'declined',
                'declined_at' => now(),
                'decline_reason' => $data['reason'],
            ]);
            if (!$asset->final_path) {
                $asset->update(['status' => 'needs_transfer']);
            }
            app(AssetEventService::class)->add(
                $asset,
                'TRANSFER_DECLINED',
                'Mitra tujuan menolak pengalihan',
                $data['reason'],
                ['partner' => $lockedTransfer->toPartner?->business_name]
            );

            return $asset;
        });

        app(NotificationService::class)->send(
            $transfer->fromPartner->user,
            'Pengalihan ditolak mitra tujuan',
            $transfer->toPartner->business_name . ' belum dapat menerima ' . $asset->passport_code . '. Pilih mitra lanjutan lain.',
            route('partner.assets.show', $asset)
        );
        app(NotificationService::class)->send(
            $asset->owner,
            'Pengalihan belum berhasil',
            'Mitra tujuan belum dapat menerima ' . $asset->passport_code . '. Barang masih menjadi tanggung jawab mitra sebelumnya sampai mitra lanjutan lain menerima.',
            route('user.assets.show', $asset)
        );

        return redirect()->route('partner.dashboard')->with('success', 'Pengalihan ditolak. Barang tetap berada pada mitra asal.');
    }

    public function cancel(Request $request, PartnerTransfer $transfer)
    {
        $source = $this->profile();
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $asset = DB::transaction(function () use ($transfer, $source, $data) {
            $lockedTransfer = PartnerTransfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedTransfer->from_partner_id === $source->id, 403);
            abort_unless($lockedTransfer->status === 'pending', 422, 'Pengalihan ini sudah diproses.');
            $asset = Asset::whereKey($lockedTransfer->asset_id)->lockForUpdate()->firstOrFail();
            abort_unless(
                AssetCustody::where('asset_id', $asset->id)->where('partner_profile_id', $source->id)->whereNull('released_at')->exists(),
                422,
                'Barang tidak lagi berada pada tanggung jawab mitra Anda.'
            );

            $lockedTransfer->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $data['reason'],
            ]);
            if (!$asset->final_path) {
                $asset->update(['status' => 'needs_transfer']);
            }
            app(AssetEventService::class)->add(
                $asset,
                'TRANSFER_CANCELLED',
                'Pengalihan dibatalkan mitra asal',
                $data['reason'],
                ['partner' => $lockedTransfer->toPartner?->business_name]
            );

            return $asset;
        });

        app(NotificationService::class)->send(
            $transfer->toPartner->user,
            'Pengalihan dibatalkan',
            'Pengalihan ' . $asset->passport_code . ' dibatalkan oleh mitra asal.'
        );

        return redirect()->route('partner.assets.show', $asset)
            ->with('success', 'Pengalihan dibatalkan. Pilih mitra lanjutan lain bila masih diperlukan.');
    }

    private function profile(): PartnerProfile
    {
        $profile = auth()->user()->partnerProfile;
        abort_unless($profile && $profile->verification_status === 'approved', 403, 'Mitra belum terverifikasi.');
        return $profile;
    }

    private function assertSourceOwnsAsset(Asset $asset, PartnerProfile $source): void
    {
        abort_if($asset->final_path, 422, 'Barang ini sudah memiliki hasil akhir.');
        abort_unless(
            AssetCustody::where('asset_id', $asset->id)->where('partner_profile_id', $source->id)->whereNull('released_at')->exists(),
            403,
            'Barang ini tidak sedang berada dalam tanggung jawab mitra Anda.'
        );
        abort_if(
            PartnerTransfer::where('asset_id', $asset->id)->where('status', 'pending')->exists(),
            422,
            'Sudah ada pengalihan yang sedang menunggu respons mitra tujuan.'
        );
    }

    private function latestAssessmentByPartner(Asset $asset, PartnerProfile $profile): ?AssetAssessment
    {
        return $asset->assessments()
            ->where('assessment_type', 'partner')
            ->where('assessor_user_id', $profile->user_id)
            ->latest('id')
            ->first();
    }

    private function previousPartnerId(Asset $asset, int $currentPartnerId): ?int
    {
        $value = AssetCustody::where('asset_id', $asset->id)
            ->where('partner_profile_id', '!=', $currentPartnerId)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->value('partner_profile_id');

        return $value ? (int) $value : null;
    }

    private function eligibleTargets(Asset $asset, PartnerProfile $source, string $requiredCapability)
    {
        $coverageIds = app(PartnerMatchingService::class)->categoryCoverageIds($asset);

        $query = PartnerProfile::with(['capabilities', 'acceptedCategories'])
            ->where('verification_status', 'approved')
            ->where('admin_status', 'active')
            ->whereKeyNot($source->id)
            ->where('accepting_requests', true)
            ->whereHas('acceptedCategories', fn($q) => $q->whereIn('device_categories.id', $coverageIds))
            ->whereHas('capabilities', fn($q) => $q->where('capability', $requiredCapability)->where('status', 'approved'));

        // Jangan langsung memantulkan barang ke mitra yang baru saja menyerahkannya.
        // Jika layanan lama memang perlu ditinjau ulang, harus ada tahap/mitra lain atau
        // koreksi assessment yang jelas; direct bounce membuat chain-of-custody berputar.
        $previousPartnerId = $this->previousPartnerId($asset, $source->id);

        if ($previousPartnerId) {
            $query->whereKeyNot($previousPartnerId);
        }

        return $query->orderBy('business_name');
    }
}
