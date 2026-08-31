<?php

namespace App\Http\Controllers;

use App\Models\{Asset, HandoverRequest, PartnerTransfer};
use App\Services\ImpactService;
use Illuminate\Http\Request;

class PartnerDashboardController extends Controller
{
    public function index()
    {
        $profile = auth()->user()->partnerProfile;
        if (!$profile) {
            return redirect()->route('partner.onboarding.create');
        }

        // Dashboard hanya menampilkan pekerjaan warga yang masih aktif.
        // Penyerahan berstatus completed adalah riwayat serah-terima dan tidak boleh
        // dipakai sebagai pintasan ke halaman penanganan ketika custody sudah pindah.
        $requests = HandoverRequest::with('asset.category')
            ->where('partner_profile_id', $profile->id)
            ->whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)
            ->latest()
            ->limit(8)
            ->get();

        $incomingTransfers = PartnerTransfer::with(['asset.assessments', 'fromPartner'])
            ->where('to_partner_id', $profile->id)
            ->where('status', 'pending')
            ->latest('requested_at')
            ->get();

        return view('partner.dashboard', [
            'profile' => $profile->load(['capabilities', 'acceptedCategories']),
            'requests' => $requests,
            'impact' => app(ImpactService::class)->summary($profile->id),
            'incomingTransfers' => $incomingTransfers,
            'handledCount' => Asset::whereNull('final_path')
                ->whereHas('custody', fn($q) => $q
                    ->where('partner_profile_id', $profile->id)
                    ->whereNull('released_at'))
                ->count(),
        ]);
    }

    public function availability(Request $request)
    {
        $profile = $request->user()->partnerProfile;
        if (!$profile) {
            return redirect()->route('partner.onboarding.create')->with('warning', 'Lengkapi profil mitra terlebih dahulu.');
        }
        if ($profile->verification_status !== 'approved') {
            return back()->with('warning', 'Profil mitra belum disetujui admin, sehingga belum dapat menerima permintaan baru.');
        }
        if (($profile->admin_status ?? 'inactive') !== 'active') {
            return back()->with('warning', 'Profil mitra sedang dinonaktifkan admin. Hubungi admin jika status ini perlu ditinjau.');
        }
        $profile->update(['accepting_requests' => $request->boolean('accepting_requests')]);

        return back()->with('success', $profile->accepting_requests
            ? 'Mitra kembali menerima permintaan baru.'
            : 'Penerimaan permintaan baru dijeda.');
    }
}
