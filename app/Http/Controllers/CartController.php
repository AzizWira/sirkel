<?php

namespace App\Http\Controllers;

use App\Models\{Asset, IntakeSession, IntakeSessionItem};
use App\Services\{AssetEventService, IntakeSessionStateService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $sessionState = app(IntakeSessionStateService::class);
        $sessions = IntakeSession::query()
            ->with(['items.asset.category', 'items.asset.requests'])
            ->where('user_id', $user->id)
            ->whereIn('status', [IntakeSession::STATUS_DRAFT, IntakeSession::STATUS_QUESTIONNAIRE, IntakeSession::STATUS_REVIEW])
            ->latest('id')
            ->get()
            ->each(fn (IntakeSession $session) => $sessionState->reconcile($session))
            ->filter(fn (IntakeSession $session) => in_array($session->status, [
                IntakeSession::STATUS_DRAFT,
                IntakeSession::STATUS_QUESTIONNAIRE,
                IntakeSession::STATUS_REVIEW,
            ], true))
            ->values();

        return view('user.cart.index', [
            'assets' => Asset::query()
                ->with(['category.group', 'photos'])
                ->where('owner_user_id', $user->id)
                ->where('status', 'cart')
                ->latest('id')
                ->get(),
            'sessions' => $sessions,
        ]);
    }

    public function process(Request $request)
    {
        $data = $request->validate([
            'asset_ids' => 'required|array|min:1|max:3',
            'asset_ids.*' => 'required|integer|distinct',
        ]);

        $ids = array_values(array_map('intval', $data['asset_ids']));
        $assets = Asset::query()
            ->whereIn('id', $ids)
            ->where('owner_user_id', $request->user()->id)
            ->where('status', 'cart')
            ->get()
            ->keyBy('id');

        if ($assets->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'asset_ids' => 'Ada barang yang sudah tidak tersedia di keranjang. Muat ulang halaman lalu pilih kembali.',
            ]);
        }

        $activeAssetIds = IntakeSessionItem::query()
            ->whereIn('asset_id', $ids)
            ->whereHas('session', fn ($q) => $q->whereNotIn('status', [IntakeSession::STATUS_COMPLETED, IntakeSession::STATUS_CANCELLED, IntakeSession::STATUS_CARTED]))
            ->pluck('asset_id')
            ->all();
        if ($activeAssetIds) {
            throw ValidationException::withMessages([
                'asset_ids' => 'Salah satu barang sudah berada dalam proses pemeriksaan lain. Lanjutkan sesi yang aktif terlebih dahulu.',
            ]);
        }

        $session = DB::transaction(function () use ($request, $ids, $assets) {
            $session = IntakeSession::create([
                'user_id' => $request->user()->id,
                'mode' => IntakeSession::MODE_STANDARD,
                'status' => IntakeSession::STATUS_QUESTIONNAIRE,
                'current_position' => 1,
            ]);

            foreach ($ids as $index => $id) {
                $asset = $assets[$id];
                IntakeSessionItem::create([
                    'intake_session_id' => $session->id,
                    'asset_id' => $asset->id,
                    'source' => 'cart',
                    'sort_order' => $index + 1,
                ]);
                $asset->update(['status' => 'registered']);
                app(AssetEventService::class)->add($asset, 'REGISTERED', 'Barang mulai diproses', 'Barang dipilih dari Keranjang Elektronik untuk cek kondisi.');
            }
            return $session;
        });

        return redirect()->route('user.intake.standard.show', $session)
            ->with('success', count($ids).' kelompok barang siap diperiksa. Jawaban akan disimpan selama proses.');
    }
}
