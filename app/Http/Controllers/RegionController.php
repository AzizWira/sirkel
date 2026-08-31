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
}
