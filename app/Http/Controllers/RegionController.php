<?php
namespace App\Http\Controllers;
use App\Services\RegionService;
use Illuminate\Http\Request;
class RegionController extends Controller
{
    public function districts()
    {
        return response()->json(app(RegionService::class)->surabayaDistricts()->map(fn($x) => ['name' => $x->name])->values());
    }
    public function villages(Request $r)
    {
        $d = $r->validate(['district' => 'required|string|max:100']);
        return response()->json(app(RegionService::class)->villages($d['district'])->map(fn($x) => ['name' => $x->name])->values());
    }

    public function reverse(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $location = app(RegionService::class)->reverseGeocode(
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if (! $location) {
            return response()->json([
                'matched' => false,
                'message' => 'Wilayah belum dapat dikenali otomatis. Pilih Kecamatan dan Kelurahan secara manual.',
            ]);
        }

        return response()->json($location + [
            'matched' => true,
            'message' => 'Kecamatan dan Kelurahan berhasil diisi dari titik lokasi.',
        ]);
    }
}
