<?php

namespace App\Http\Controllers;

use App\Services\RegionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function completeForm()
    {
        return view('auth.complete-profile', ['districts' => app(RegionService::class)->surabayaDistricts()]);
    }

    public function complete(Request $request)
    {
        $data = $this->validatedProfile($request);
        $data['whatsapp'] = $this->phone($data['whatsapp']);
        $request->user()->update($data + ['profile_completed_at' => now()]);

        return redirect($this->homeFor($request->user()))->with('success', 'Profil berhasil dilengkapi.');
    }

    public function edit(Request $request)
    {
        return view('user.profile.edit', [
            'user' => $request->user(),
            'districts' => app(RegionService::class)->surabayaDistricts(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatedProfile($request);
        $data['whatsapp'] = $this->phone($data['whatsapp']);
        $request->user()->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }

    public function theme(Request $request)
    {
        $data = $request->validate(['theme' => 'required|in:light,dark,system']);
        $request->user()->update(['theme_preference' => $data['theme']]);
        return response()->json(['ok' => true]);
    }

    private function validatedProfile(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'whatsapp' => 'required|string|max:24',
            'district' => 'required|string|max:100',
            'village' => 'required|string|max:100',
        ]);

        $regions = app(RegionService::class);
        if (!$regions->isValidSurabayaLocation($data['district'], $data['village'])) {
            throw ValidationException::withMessages([
                'village' => 'Kelurahan tidak sesuai dengan kecamatan yang dipilih. Pilih kembali dari daftar.',
            ]);
        }

        $normalized = $regions->normalizeLocation($data['district'], $data['village']);
        $data['district'] = $normalized['district'];
        $data['village'] = $normalized['village'];

        return $data;
    }

    private function phone(string $value): string
    {
        $number = preg_replace('/\D+/', '', $value);
        if (str_starts_with($number, '0'))
            $number = '62' . substr($number, 1);
        elseif (!str_starts_with($number, '62'))
            $number = '62' . $number;
        return $number;
    }

    private function homeFor($user): string
    {
        return $user->isAdmin()
            ? route('admin.dashboard')
            : ($user->isPartner() ? route('partner.dashboard') : route('user.dashboard'));
    }
}
