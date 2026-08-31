<?php

namespace App\Http\Controllers;

use App\Enums\PartnerCapability;
use App\Models\{DeviceCategory, PartnerCapabilityModel, PartnerProfile};
use App\Services\RegionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PartnerOnboardingController extends Controller
{
    public function create(Request $request)
    {
        $profile = $request->user()->partnerProfile?->load(['capabilities', 'acceptedCategories']);
        $partnerMode = $request->user()->activeAccessRole() === 'partner';

        // Dari mode Warga, pengajuan yang pending/approved ditampilkan sebagai
        // halaman status. Rejected boleh diperbaiki dan dikirim ulang.
        $statusOnly = !$partnerMode
            && $profile
            && in_array($profile->verification_status, ['pending', 'approved'], true);

        $reviewNote = $profile?->capabilities
                ?->pluck('review_note')
            ->filter(fn($note) => filled($note))
            ->first();

        return view('partner.onboarding.create', [
            'profile' => $profile,
            'statusOnly' => $statusOnly,
            'partnerMode' => $partnerMode,
            'reviewNote' => $reviewNote,
            'capabilities' => PartnerCapability::cases(),
            'categories' => DeviceCategory::with('group')->where('active', true)->get()
                ->sortBy(fn($category) => sprintf('%03d-%03d', $category->group?->sort_order ?? 999, $category->sort_order))
                ->values(),
            'districts' => app(RegionService::class)->surabayaDistricts(),
        ]);
    }

    public function store(Request $request)
    {
        $existing = $request->user()->partnerProfile;
        $partnerMode = $request->user()->activeAccessRole() === 'partner';

        if (!$partnerMode && $existing?->partner_access_granted_at) {
            throw ValidationException::withMessages([
                'partner' => 'Akses Mitra sudah tersedia pada akun Anda. Masuk sebagai Mitra untuk mengubah profil operasional.',
            ]);
        }

        $data = $request->validate([
            'business_name' => 'required|string|max:150',
            'responsible_name' => 'required|string|max:100',
            'phone' => 'required|string|max:24',
            'address' => 'required|string|max:500',
            'district' => 'required|string|max:100',
            'village' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'pickup_radius_km' => 'required|numeric|min:1|max:100',
            'capabilities' => 'required|array|min:1',
            'capabilities.*' => 'required|string',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:device_categories,id',
            'ktp' => [$existing && $existing->identity_file_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'place_photo' => [$existing && $existing->place_photo_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'operating_hours' => 'nullable|string|max:500',
        ], [
            'capabilities.required' => 'Pilih minimal satu layanan yang dapat dilakukan mitra Anda.',
            'capabilities.min' => 'Pilih minimal satu layanan yang dapat dilakukan mitra Anda.',
            'category_ids.required' => 'Pilih minimal satu kategori barang yang dapat Anda terima.',
            'category_ids.min' => 'Pilih minimal satu kategori barang yang dapat Anda terima.',
            'ktp.required' => 'Unggah foto KTP penanggung jawab untuk proses verifikasi.',
            'ktp.image' => 'Foto KTP harus berupa file gambar.',
            'ktp.mimes' => 'Foto KTP harus berformat JPG, JPEG, PNG, atau WebP.',
            'ktp.max' => 'Ukuran foto KTP maksimal 5 MB.',
            'ktp.uploaded' => 'Foto KTP gagal diunggah. Pilih file kembali dan pastikan ukurannya maksimal 5 MB.',
            'place_photo.required' => 'Unggah foto tempat operasional mitra.',
            'place_photo.image' => 'Foto tempat operasional harus berupa file gambar.',
            'place_photo.mimes' => 'Foto tempat operasional harus berformat JPG, JPEG, PNG, atau WebP.',
            'place_photo.max' => 'Ukuran foto tempat operasional maksimal 5 MB.',
            'place_photo.uploaded' => 'Foto tempat operasional gagal diunggah. Pilih file kembali dan pastikan ukurannya maksimal 5 MB.',
            'latitude.between' => 'Titik lokasi belum valid. Pilih titik pada peta atau gunakan tombol “Ambil lokasi saya”.',
            'longitude.between' => 'Titik lokasi belum valid. Pilih titik pada peta atau gunakan tombol “Ambil lokasi saya”.',
        ]);

        $regionService = app(RegionService::class);
        if (!$regionService->isValidSurabayaLocation($data['district'], $data['village'])) {
            throw ValidationException::withMessages([
                'village' => 'Kelurahan tidak sesuai dengan kecamatan yang dipilih. Pilih kembali dari daftar.',
            ]);
        }

        $normalized = $regionService->normalizeLocation($data['district'], $data['village']);
        $data['district'] = $normalized['district'];
        $data['village'] = $normalized['village'];

        $allowed = collect(PartnerCapability::cases())->map(fn($capability) => $capability->value)->all();
        foreach ($data['capabilities'] as $capability) {
            if (!in_array($capability, $allowed, true)) {
                throw ValidationException::withMessages([
                    'capabilities' => 'Ada layanan yang tidak dikenali. Muat ulang halaman lalu pilih layanan kembali.',
                ]);
            }
        }

        $ktp = $existing?->identity_file_path;
        if ($request->hasFile('ktp')) {
            if ($ktp)
                Storage::disk('local')->delete($ktp);
            $ktp = $request->file('ktp')->store('identity', 'local');
        }

        $placePhoto = $existing?->place_photo_path;
        if ($request->hasFile('place_photo')) {
            if ($placePhoto)
                Storage::disk('public')->delete($placePhoto);
            $placePhoto = $request->file('place_photo')->store('partners', 'public');
        }

        $profile = PartnerProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'business_name' => $data['business_name'],
                'responsible_name' => $data['responsible_name'],
                'phone' => $this->phone($data['phone']),
                'address' => $data['address'],
                'district' => $data['district'],
                'village' => $data['village'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'pickup_radius_km' => $data['pickup_radius_km'],
                'verification_status' => 'pending',
                'admin_status' => 'inactive',
                'identity_file_path' => $ktp,
                'identity_delete_after' => null,
                'identity_deleted_at' => null,
                'place_photo_path' => $placePhoto,
                'accepting_requests' => false,
                'operating_hours_json' => ['display' => $data['operating_hours'] ?? 'Senin–Sabtu, 08.00–17.00'],
                // Entitlement yang sudah pernah diberikan tidak dicabut hanya karena
                // mitra mengirim perubahan profil untuk ditinjau ulang.
                'partner_access_granted_at' => $existing?->partner_access_granted_at,
                'approval_acknowledged_at' => $existing?->approval_acknowledged_at,
            ]
        );

        $profile->acceptedCategories()->sync($data['category_ids']);
        $profile->capabilities()->delete();
        foreach ($data['capabilities'] as $capability) {
            PartnerCapabilityModel::create([
                'partner_profile_id' => $profile->id,
                'capability' => $capability,
                'status' => 'pending',
            ]);
        }

        $request->user()->update([
            'profile_completed_at' => $request->user()->profile_completed_at ?: now(),
        ]);

        if ($partnerMode) {
            return redirect()->route('partner.dashboard')
                ->with('success', 'Perubahan profil dikirim untuk ditinjau admin. Akses Mitra tetap tersimpan, tetapi permintaan baru dijeda selama peninjauan.');
        }

        return redirect()->route('user.become-partner.create')
            ->with('success', 'Pengajuan mitra dikirim. Anda tetap dapat menggunakan SIRKEL sebagai warga selama proses verifikasi.');
    }

    public function acknowledgeApproval(Request $request)
    {
        $profile = $request->user()->partnerProfile;
        abort_unless($profile?->partner_access_granted_at, 422, 'Akses Mitra belum disetujui.');

        if (!$profile->approval_acknowledged_at) {
            $profile->update(['approval_acknowledged_at' => now()]);
        }

        return redirect()->route('user.dashboard')
            ->with('success', 'Pengajuan Mitra sudah disetujui. Keluar lalu masuk kembali untuk memilih masuk sebagai Warga atau Mitra.');
    }

    private function phone(string $value): string
    {
        $normalized = preg_replace('/\D+/', '', $value);
        if (str_starts_with($normalized, '0')) {
            $normalized = '62' . substr($normalized, 1);
        } elseif (!str_starts_with($normalized, '62')) {
            $normalized = '62' . $normalized;
        }
        return $normalized;
    }
}
