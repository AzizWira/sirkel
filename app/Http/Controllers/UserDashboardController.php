<?php
namespace App\Http\Controllers;
use App\Models\Asset;
use App\Services\ImpactService;
class UserDashboardController extends Controller
{
    public function index()
    {
        return view('user.dashboard', ['assets' => Asset::with('category')->where('owner_user_id', auth()->id())->whereNotIn('status', ['cart', 'bulk_draft'])->latest()->limit(6)->get(), 'impact' => app(ImpactService::class)->summary(null, auth()->id())]);
    }
    public function activity()
    {
        return view('user.activity', ['events' => \App\Models\AssetEvent::whereHas('asset', fn($q) => $q->where('owner_user_id', auth()->id()))->with('asset')->latest('occurred_at')->paginate(20)]);
    }
    public function impact()
    {
        return view('user.impact', ['impact' => app(ImpactService::class)->summary(null, auth()->id())]);
    }
}
