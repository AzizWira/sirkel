<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\{Asset, HandoverRequest, IssueReport, User};
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IssueController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'nullable|exists:assets,id',
            'handover_request_id' => 'nullable|exists:handover_requests,id',
            'category' => 'required|in:partner_no_show,user_unreachable,item_mismatch,value_problem,behavior,no_update,matching_help,other',
            'description' => 'required|string|max:1500',
            'matching_help_authorization' => 'nullable|required_if:category,matching_help|accepted',
        ]);

        $user = $request->user();
        $asset = null;
        if (!empty($data['asset_id'])) {
            $asset = Asset::findOrFail($data['asset_id']);
            $isOwner = $asset->owner_user_id === $user->id;
            $isCustodyPartner = $user->isPartner() && $asset->custody()->where('partner_profile_id', $user->partnerProfile?->id)->exists();
            abort_unless($isOwner || $isCustodyPartner || $user->isAdmin(), 403);
        }
        if (!empty($data['handover_request_id'])) {
            $handover = HandoverRequest::findOrFail($data['handover_request_id']);
            $isOwner = $handover->user_id === $user->id;
            $isPartner = $user->isPartner() && $handover->partner_profile_id === $user->partnerProfile?->id;
            abort_unless($isOwner || $isPartner || $user->isAdmin(), 403);
        }

        $context = null;
        $existingMatchingHelp = null;
        if (($data['category'] ?? null) === 'matching_help') {
            abort_unless($asset && $asset->owner_user_id === $user->id, 403);

            $existingMatchingHelp = IssueReport::query()
                ->where('reporter_user_id', $user->id)
                ->where('asset_id', $asset->id)
                ->where('category', 'matching_help')
                ->whereIn('status', ['open', 'in_review'])
                ->latest()
                ->first();

            if ($existingMatchingHelp && is_array($existingMatchingHelp->context_json)) {
                return back()->with('info', 'Permintaan bantuan untuk barang ini masih ditangani SIRKEL.');
            }

            $matchData = $request->session()->get('handover_match.' . $asset->id);
            if (!is_array($matchData)) {
                throw ValidationException::withMessages([
                    'matching_help_authorization' => 'Pilih kembali cara penyerahan sebelum meminta bantuan SIRKEL.',
                ]);
            }

            $context = array_intersect_key($matchData, array_flip([
                'method',
                'handover_type',
                'latitude',
                'longitude',
                'address',
                'district',
                'village',
                'requested_date',
                'time_start',
                'time_end',
            ]));
            $context['authorized_at'] = now()->toIso8601String();
        }

        unset($data['matching_help_authorization']);
        if ($existingMatchingHelp) {
            $existingMatchingHelp->update([
                'description' => $data['description'],
                'context_json' => $context,
                'status' => 'open',
                'handover_request_id' => null,
                'resolved_by' => null,
                'resolved_at' => null,
            ]);
            $issue = $existingMatchingHelp->fresh();
        } else {
            $issue = IssueReport::create($data + [
                'reporter_user_id' => $user->id,
                'status' => 'open',
                'context_json' => $context,
            ]);
        }

        if ($issue->category === 'matching_help') {
            User::query()->where('role', UserRole::ADMIN->value)->get()->each(function (User $admin) use ($issue, $asset) {
                app(NotificationService::class)->send(
                    $admin,
                    'Warga meminta bantuan mencari mitra',
                    'Perlu bantuan mencarikan mitra untuk ' . $asset->passport_code . '.',
                    route('admin.issues.index'),
                    false
                );
            });
        }

        $message = $issue->category === 'matching_help'
            ? 'Permintaan bantuan dikirim. SIRKEL akan membantu mencarikan mitra dan memberi kabar saat ada perkembangan.'
            : 'Laporan diterima dan masuk antrean moderasi admin.';

        return back()->with('success', $message);
    }
}
