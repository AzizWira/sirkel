<?php

namespace App\Http\Controllers;

use App\Models\AiTopupRequest;
use App\Services\AiQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiQuotaController extends Controller
{
    public function index(Request $request, AiQuotaService $quota)
    {
        $user = $request->user();
        $pending = $quota->pendingRequest($user);

        return view('user.ai-quota.index', [
            'quotas' => $quota->all($user),
            'pending' => $pending,
            'pendingWhatsappUrl' => $pending ? $quota->whatsappUrl($pending) : null,
            'requests' => AiTopupRequest::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request, AiQuotaService $quota)
    {
        $data = $request->validate([
            'asset_intake_quantity' => 'nullable|integer|min:0|max:500',
            'condition_description_quantity' => 'nullable|integer|min:0|max:1000',
            'bulk_ai_quantity' => 'nullable|integer|min:0|max:500',
        ]);

        $assetQty = (int) ($data['asset_intake_quantity'] ?? 0);
        $descriptionQty = (int) ($data['condition_description_quantity'] ?? 0);
        $bulkQty = (int) ($data['bulk_ai_quantity'] ?? 0);
        if ($assetQty <= 0 && $descriptionQty <= 0 && $bulkQty <= 0) {
            throw ValidationException::withMessages([
                'asset_intake_quantity' => 'Pilih minimal satu tambahan kuota sebelum membuka WhatsApp.',
            ]);
        }

        if ($existing = $quota->pendingRequest($request->user())) {
            return redirect()->route('user.ai-quota.index')
                ->with('warning', 'Masih ada permintaan top up yang menunggu keputusan admin. Kirim ulang pesan WhatsApp dari permintaan tersebut jika diperlukan.');
        }

        $definitions = $quota->definitions();
        $assetPrice = (int) $definitions[AiQuotaService::ASSET_INTAKE]['unit_price_idr'];
        $descriptionPrice = (int) $definitions[AiQuotaService::CONDITION_DESCRIPTION]['unit_price_idr'];
        $bulkPrice = (int) $definitions[AiQuotaService::BULK_AI]['unit_price_idr'];
        $total = ($assetQty * $assetPrice) + ($descriptionQty * $descriptionPrice) + ($bulkQty * $bulkPrice);

        $topup = DB::transaction(function () use ($request, $assetQty, $descriptionQty, $bulkQty, $assetPrice, $descriptionPrice, $bulkPrice, $total) {
            $topup = AiTopupRequest::create([
                'user_id' => $request->user()->id,
                'status' => AiTopupRequest::STATUS_PENDING,
                'asset_intake_quantity' => $assetQty,
                'condition_description_quantity' => $descriptionQty,
                'bulk_ai_quantity' => $bulkQty,
                'asset_intake_unit_price_idr' => $assetPrice,
                'condition_description_unit_price_idr' => $descriptionPrice,
                'bulk_ai_unit_price_idr' => $bulkPrice,
                'total_amount_idr' => $total,
                'requested_at' => now(),
            ]);

            $user = $request->user();
            $code = 'TP-' . strtoupper(substr((string) $topup->public_id, -8));
            $reviewLink = route('ai-topups.resolve', $topup->public_id);
            $lines = [
                'Halo Admin SIRKEL, saya ingin mengajukan penambahan Kuota AI.',
                '',
                'Nama: ' . $user->name,
                'Email: ' . $user->email,
                'Nomor HP: ' . ($user->whatsapp ?: '-'),
                '',
                'Permintaan Kuota:',
                'Pengenalan Barang: +' . $assetQty . ' kali',
                'Penyusunan Catatan Kondisi: +' . $descriptionQty . ' kali',
                'Bulk AI: +' . $bulkQty . ' sesi',
                '',
                'Total: Rp' . number_format($total, 0, ',', '.'),
                'Kode Permintaan: ' . $code,
                '',
                'Detail permintaan:',
                $reviewLink,
                '',
                '⚠️ PENTING: DILARANG mengubah, menghapus, menambahkan, atau menyusun ulang format pesan ini. Mohon kirim pesan apa adanya agar kode permintaan dan data top up dapat diproses dengan benar oleh Admin SIRKEL.',
            ];

            $topup->update(['whatsapp_message' => implode("\n", $lines)]);
            return $topup;
        });

        $whatsappUrl = $quota->whatsappUrl($topup);
        if (!$whatsappUrl) {
            return redirect()->route('user.ai-quota.index')
                ->with('warning', 'Permintaan sudah dibuat, tetapi nomor WhatsApp admin belum dikonfigurasi. Hubungi admin dan sebutkan kode permintaan Anda.');
        }

        return redirect()->away($whatsappUrl);
    }
}
