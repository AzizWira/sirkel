<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\AiTopupRequest;
use App\Services\{AiQuotaService, AuditService, NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAiQuotaController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'pending');
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $query = AiTopupRequest::query()->with('user', 'reviewer')->latest('id');
        if ($status !== 'all')
            $query->where('status', $status);

        return view('admin.ai-quota.index', [
            'requests' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'pendingCount' => AiTopupRequest::where('status', AiTopupRequest::STATUS_PENDING)->count(),
            'approvedCount' => AiTopupRequest::where('status', AiTopupRequest::STATUS_APPROVED)->count(),
            'approvedRevenue' => (int) AiTopupRequest::where('status', AiTopupRequest::STATUS_APPROVED)->sum('total_amount_idr'),
        ]);
    }

    public function show(AiTopupRequest $topup, AiQuotaService $quota)
    {
        $topup->load('user', 'reviewer');
        return view('admin.ai-quota.show', [
            'topup' => $topup,
            'quotas' => $quota->all($topup->user),
        ]);
    }

    public function approve(Request $request, AiTopupRequest $topup)
    {
        $before = $topup->toArray();

        DB::transaction(function () use ($request, $topup) {
            $locked = AiTopupRequest::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if (!$locked->isPending()) {
                throw ValidationException::withMessages([
                    'topup' => 'Permintaan ini sudah diproses sebelumnya.',
                ]);
            }

            $locked->update([
                'status' => AiTopupRequest::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => null,
            ]);
        });

        $fresh = $topup->fresh()->load('user');
        app(AuditService::class)->log('ai.topup.approved', $fresh, $before, $fresh->toArray());
        app(NotificationService::class)->send(
            $fresh->user,
            'Top up Kuota AI disetujui',
            'Tambahan kuota AI Anda sudah aktif dan dapat langsung digunakan.',
            route('user.ai-quota.index'),
            false
        );

        return redirect()->route('admin.ai-quota.show', $fresh)->with('success', 'Top up disetujui. Kuota tambahan langsung aktif.');
    }

    public function reject(Request $request, AiTopupRequest $topup)
    {
        $data = $request->validate(['reason' => 'required|string|min:3|max:500']);
        $before = $topup->toArray();

        DB::transaction(function () use ($request, $topup, $data) {
            $locked = AiTopupRequest::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if (!$locked->isPending()) {
                throw ValidationException::withMessages([
                    'topup' => 'Permintaan ini sudah diproses sebelumnya.',
                ]);
            }

            $locked->update([
                'status' => AiTopupRequest::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'rejection_reason' => $data['reason'],
            ]);
        });

        $fresh = $topup->fresh()->load('user');
        app(AuditService::class)->log('ai.topup.rejected', $fresh, $before, $fresh->toArray());
        app(NotificationService::class)->send(
            $fresh->user,
            'Permintaan top up Kuota AI belum disetujui',
            'Admin belum menyetujui permintaan top up Anda. Alasan: ' . $fresh->rejection_reason,
            route('user.ai-quota.index'),
            false
        );

        return redirect()->route('admin.ai-quota.show', $fresh)->with('success', 'Permintaan top up ditolak.');
    }

    /**
     * Tautan yang dikirim melalui WhatsApp sengaja tidak memakai prefix /admin.
     * Kode hanya pointer. Otorisasi admin tetap wajib sebelum detail request dibuka.
     */
    public function resolve(Request $request, string $code)
    {
        abort_unless($request->user()?->role === UserRole::ADMIN, 404);

        $topup = AiTopupRequest::query()->where('public_id', $code)->firstOrFail();
        return redirect()->route('admin.ai-quota.show', $topup);
    }
}
