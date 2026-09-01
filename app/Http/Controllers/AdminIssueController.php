<?php

namespace App\Http\Controllers;

use App\Models\{Asset, HandoverRequest, IssueReport, PartnerProfile};
use App\Services\{AssetEventService, AuditService, NotificationService, PartnerMatchingService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminIssueController extends Controller
{
    public function index()
    {
        $issues = IssueReport::with([
            'reporter',
            'asset.category.group',
            'request.partner',
        ])->latest()->paginate(25);

        $candidateMap = collect();
        foreach ($issues->getCollection() as $issue) {
            if ($issue->category !== 'matching_help' || !in_array($issue->status, ['open', 'in_review'], true)) {
                continue;
            }

            $context = $issue->context_json;
            if (!$issue->asset || !is_array($context)) {
                continue;
            }

            $hasActiveRequest = $issue->asset->requests()
                ->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)
                ->exists();
            if ($hasActiveRequest) {
                continue;
            }

            $candidates = app(PartnerMatchingService::class)->assistanceCandidates(
                $issue->asset,
                (string) ($context['method'] ?? 'dropoff'),
                (float) ($context['latitude'] ?? 0),
                (float) ($context['longitude'] ?? 0),
                $context['district'] ?? null
            );

            if ($issue->request && in_array($issue->request->status, HandoverRequest::TERMINAL_STATUSES, true)) {
                $previousPartnerId = (int) $issue->request->partner_profile_id;
                $candidates = $candidates->sortBy(fn($partner) => (int) $partner->id === $previousPartnerId ? 1 : 0)->values();
            }

            $candidateMap->put($issue->id, $candidates);
        }

        return view('admin.issues.index', compact('issues', 'candidateMap'));
    }

    public function offerPartner(Request $request, IssueReport $issue)
    {
        $data = $request->validate([
            'partner_profile_id' => 'required|exists:partner_profiles,id',
        ]);

        abort_unless($issue->category === 'matching_help', 422, 'Aksi ini hanya tersedia untuk bantuan pencarian mitra.');
        abort_unless(in_array($issue->status, ['open', 'in_review'], true), 422, 'Permintaan bantuan ini sudah ditutup.');

        $issue->load(['asset.category.group', 'reporter', 'request']);
        $asset = $issue->asset;
        $context = $issue->context_json;
        abort_unless($asset && is_array($context), 422, 'Data penyerahan pada permintaan bantuan ini belum lengkap.');
        abort_unless(filled($context['authorized_at'] ?? null), 422, 'Persetujuan warga untuk bantuan pencarian mitra belum tercatat.');

        abort_if(
            $asset->requests()->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->exists(),
            422,
            'Barang ini sudah memiliki permintaan aktif. Tunggu hasil permintaan tersebut terlebih dahulu.'
        );

        $partner = PartnerProfile::with(['user', 'capabilities', 'acceptedCategories'])->findOrFail($data['partner_profile_id']);
        abort_unless(
            app(PartnerMatchingService::class)->supportsAssistedRequest($partner, $asset, (string) $context['method']),
            422,
            'Mitra ini tidak lagi tersedia untuk layanan utama yang dibutuhkan barang.'
        );

        $handover = DB::transaction(function () use ($issue, $asset, $partner, $context, $request) {
            $lockedIssue = IssueReport::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            $lockedAsset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $lockedPartner = PartnerProfile::whereKey($partner->id)->lockForUpdate()->firstOrFail();

            abort_if(
                HandoverRequest::where('asset_id', $lockedAsset->id)
                    ->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)
                    ->exists(),
                422,
                'Barang ini sudah memiliki permintaan aktif.'
            );
            abort_unless(
                app(PartnerMatchingService::class)->supportsAssistedRequest($lockedPartner, $lockedAsset, (string) $context['method']),
                422,
                'Mitra ini tidak lagi tersedia untuk layanan utama yang dibutuhkan barang.'
            );

            $distance = app(PartnerMatchingService::class)->haversine(
                (float) $context['latitude'],
                (float) $context['longitude'],
                (float) $lockedPartner->latitude,
                (float) $lockedPartner->longitude
            );
            $pickup = ($context['method'] ?? null) === 'pickup';

            $requestedDate = $this->usableRequestedDate($context['requested_date'] ?? null);
            $timeStart = $requestedDate ? ($context['time_start'] ?? null) : null;
            $timeEnd = $requestedDate ? ($context['time_end'] ?? null) : null;

            $handover = HandoverRequest::create([
                'asset_id' => $lockedAsset->id,
                'user_id' => $lockedAsset->owner_user_id,
                'partner_profile_id' => $lockedPartner->id,
                'method' => $context['method'],
                'handover_type' => $context['handover_type'],
                'ownership_acknowledged_at' => Carbon::parse($context['authorized_at']),
                'status' => 'pending',
                'pickup_latitude' => $context['latitude'],
                'pickup_longitude' => $context['longitude'],
                'pickup_address' => $pickup ? ($context['address'] ?? null) : null,
                'pickup_district' => $context['district'] ?? null,
                'pickup_village' => $context['village'] ?? null,
                'distance_km' => round($distance, 2),
                'within_radius' => !$pickup || $distance <= (float) $lockedPartner->pickup_radius_km,
                'outside_radius' => $pickup && $distance > (float) $lockedPartner->pickup_radius_km,
                'requested_date' => $requestedDate,
                'requested_time_start' => $timeStart,
                'requested_time_end' => $timeEnd,
                'schedule_status' => 'requested',
            ]);

            $lockedAsset->update([
                'status' => 'requested',
                'handover_type' => $context['handover_type'],
            ]);

            $before = $lockedIssue->toArray();
            $lockedIssue->update([
                'handover_request_id' => $handover->id,
                'status' => 'in_review',
                'resolved_by' => null,
                'resolved_at' => null,
            ]);
            app(AuditService::class)->log('issue.matching_partner_offer', $lockedIssue, $before, $lockedIssue->fresh()->toArray());

            app(AssetEventService::class)->add(
                $lockedAsset,
                'SIRKEL_MATCH_ASSISTANCE',
                'SIRKEL membantu mencarikan mitra',
                'Admin meneruskan permintaan kepada ' . $lockedPartner->business_name . '.',
                ['issue_report_id' => $lockedIssue->id, 'partner_profile_id' => $lockedPartner->id]
            );

            return $handover;
        });

        $handover->load(['partner.user', 'asset', 'user']);
        app(NotificationService::class)->send(
            $handover->partner->user,
            'Permintaan bantuan dari SIRKEL',
            'SIRKEL meminta Anda meninjau ' . $handover->asset->passport_code . '. Terima hanya jika layanan Anda dapat menangani barang ini.',
            route('partner.requests.show', $handover)
        );
        app(NotificationService::class)->send(
            $handover->user,
            'SIRKEL sedang menghubungi mitra',
            'Permintaan untuk ' . $handover->asset->passport_code . ' sudah diteruskan ke ' . $handover->partner->business_name . '.',
            route('user.assets.show', $handover->asset)
        );

        return back()->with('success', 'Permintaan sudah diteruskan ke ' . $handover->partner->business_name . '. Tunggu tanggapan mitra.');
    }

    public function update(Request $request, IssueReport $issue)
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_review,resolved,dismissed',
            'admin_note' => 'nullable|string|max:1500',
        ]);
        $before = $issue->toArray();
        $finished = in_array($data['status'], ['resolved', 'dismissed'], true);
        $issue->update($data + [
            'resolved_by' => $finished ? $request->user()->id : null,
            'resolved_at' => $finished ? now() : null,
        ]);
        app(AuditService::class)->log('issue.moderate', $issue, $before, $issue->toArray());

        return back()->with('success', 'Status laporan diperbarui.');
    }

    private function usableRequestedDate(?string $date): ?string
    {
        if (!filled($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date)->startOfDay();
            return $parsed->lt(today()) || $parsed->gt(now()->endOfYear())
                ? null
                : $parsed->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
