<?php

namespace App\Http\Controllers;

use App\Models\PartnerProfile;
use App\Services\{AuditService, NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminPartnerController extends Controller
{
    public function index()
    {
        return view('admin.partners.index', [
            'partners' => PartnerProfile::with(['user', 'capabilities'])->latest()->paginate(20),
        ]);
    }

    public function show(PartnerProfile $partner)
    {
        return view('admin.partners.show', [
            'partner' => $partner->load(['user', 'capabilities', 'acceptedCategories']),
        ]);
    }

    public function identity(PartnerProfile $partner)
    {
        abort_unless(
            $partner->identity_file_path && Storage::disk('local')->exists($partner->identity_file_path),
            404
        );

        return Storage::disk('local')->download(
            $partner->identity_file_path,
            'KTP-verifikasi-mitra-' . $partner->id . '.' . pathinfo($partner->identity_file_path, PATHINFO_EXTENSION)
        );
    }

    public function review(Request $request, PartnerProfile $partner)
    {
        if ($partner->verification_status === 'approved') {
            throw ValidationException::withMessages([
                'decision' => 'Mitra ini sudah disetujui. Gunakan pengelolaan mitra untuk mengubah layanan atau status operasional.',
            ]);
        }

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:1000',
            'capabilities' => 'nullable|array',
            'capabilities.*' => 'string',
        ], [
            'capabilities.required' => 'Pilih minimal satu layanan mitra yang disetujui.',
        ]);

        if ($data['decision'] === 'approved' && empty($data['capabilities'])) {
            throw ValidationException::withMessages([
                'capabilities' => 'Pilih minimal satu layanan mitra yang disetujui.',
            ]);
        }

        $before = $partner->load('capabilities')->toArray();
        $approved = $data['decision'] === 'approved';
        $firstGrant = $approved && !$partner->partner_access_granted_at;

        $partner->update([
            'verification_status' => $data['decision'],
            'admin_status' => $approved ? 'active' : 'inactive',
            'verified_at' => $approved ? now() : null,
            'partner_access_granted_at' => $approved ? ($partner->partner_access_granted_at ?: now()) : $partner->partner_access_granted_at,
            'approval_acknowledged_at' => $firstGrant ? null : $partner->approval_acknowledged_at,
            'verified_by' => $request->user()->id,
            'identity_delete_after' => $partner->identity_file_path
                ? now()->addDays(config('sirkel.ktp_retention_days'))
                : null,
            'accepting_requests' => $approved,
        ]);

        $this->syncCapabilities($request, $partner, $data['capabilities'] ?? [], $data['note'] ?? null, $approved);

        app(AuditService::class)->log('partner.review', $partner, $before, $partner->fresh()->load('capabilities')->toArray());
        app(NotificationService::class)->send(
            $partner->user,
            'Status verifikasi mitra',
            $approved
            ? ($firstGrant
                ? 'Pengajuan mitra Anda disetujui. Mulai login berikutnya, Anda dapat memilih masuk sebagai Warga atau Mitra.'
                : 'Perubahan profil mitra Anda sudah disetujui dan operasional dapat dilanjutkan.')
            : ($partner->partner_access_granted_at
                ? 'Perubahan profil mitra belum disetujui. Akses Mitra tetap tersimpan, tetapi permintaan baru dinonaktifkan sampai data diperbaiki.'
                : 'Pengajuan mitra Anda ditolak. Periksa kembali data pengajuan sebelum mengirim ulang.'),
            ($firstGrant || !$partner->partner_access_granted_at) ? route('user.become-partner.create') : route('partner.dashboard')
        );

        return back()->with(
            'success',
            $approved
            ? 'Mitra disetujui dan diaktifkan. File KTP dijadwalkan terhapus sesuai masa retensi.'
            : 'Pengajuan mitra ditolak.'
        );
    }

    public function manage(Request $request, PartnerProfile $partner)
    {
        if ($partner->verification_status !== 'approved') {
            throw ValidationException::withMessages(['partner' => 'Pengajuan mitra ini belum disetujui, sehingga belum dapat dikelola sebagai mitra aktif.']);
        }

        $data = $request->validate([
            'capabilities' => 'required|array|min:1',
            'capabilities.*' => 'string',
            'note' => 'nullable|string|max:1000',
        ], [
            'capabilities.required' => 'Pilih minimal satu layanan yang tetap diizinkan untuk mitra ini.',
            'capabilities.min' => 'Pilih minimal satu layanan yang tetap diizinkan untuk mitra ini.',
        ]);

        $before = $partner->load('capabilities')->toArray();
        $this->syncCapabilities($request, $partner, $data['capabilities'], $data['note'] ?? null, true);

        app(AuditService::class)->log('partner.manage', $partner, $before, $partner->fresh()->load('capabilities')->toArray());
        app(NotificationService::class)->send(
            $partner->user,
            'Layanan mitra diperbarui',
            'Admin memperbarui layanan yang diizinkan untuk profil mitra Anda.',
            route('partner.dashboard')
        );

        return back()->with('success', 'Perubahan layanan mitra disimpan.');
    }

    public function status(Request $request, PartnerProfile $partner)
    {
        if ($partner->verification_status !== 'approved') {
            throw ValidationException::withMessages(['partner' => 'Hanya mitra yang sudah disetujui yang dapat diaktifkan atau dinonaktifkan.']);
        }

        $data = $request->validate([
            'admin_status' => 'required|in:active,inactive',
        ]);

        $before = $partner->toArray();
        $activating = $data['admin_status'] === 'active';
        $partner->update([
            'admin_status' => $data['admin_status'],
            // Saat dinonaktifkan, mitra langsung dikeluarkan dari rekomendasi. Saat
            // diaktifkan kembali, penerimaan permintaan tetap off sampai mitra memilihnya sendiri.
            'accepting_requests' => false,
        ]);

        app(AuditService::class)->log('partner.status', $partner, $before, $partner->fresh()->toArray());
        app(NotificationService::class)->send(
            $partner->user,
            $activating ? 'Mitra diaktifkan kembali' : 'Mitra dinonaktifkan',
            $activating
            ? 'Admin mengaktifkan kembali profil mitra Anda. Aktifkan penerimaan permintaan saat Anda siap menerima barang baru.'
            : 'Admin menonaktifkan profil mitra Anda dari penerimaan permintaan baru. Riwayat verifikasi tetap tersimpan.',
            route('partner.dashboard')
        );

        return back()->with(
            'success',
            $activating
            ? 'Mitra diaktifkan kembali. Penerimaan permintaan baru masih dijeda sampai mitra mengaktifkannya.'
            : 'Mitra dinonaktifkan dan tidak lagi muncul untuk permintaan baru.'
        );
    }

    private function syncCapabilities(Request $request, PartnerProfile $partner, array $selected, ?string $note, bool $approveSelected): void
    {
        $known = $partner->capabilities->pluck('capability')->all();
        if (array_diff($selected, $known)) {
            throw ValidationException::withMessages([
                'capabilities' => 'Ada layanan yang tidak termasuk dalam pengajuan mitra ini. Muat ulang halaman dan coba lagi.',
            ]);
        }

        foreach ($partner->capabilities as $capability) {
            $status = $approveSelected && in_array($capability->capability, $selected, true)
                ? 'approved'
                : 'rejected';
            $capability->update([
                'status' => $status,
                'review_note' => $note,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }
    }
}
