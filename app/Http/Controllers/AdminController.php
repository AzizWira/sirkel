<?php
namespace App\Http\Controllers;
use App\Models\{Asset, HandoverRequest, IssueReport, PartnerProfile, User};
use App\Services\ImpactService;
class AdminController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', ['counts' => ['users' => User::where('role', '!=', 'admin')->count(), 'partners' => PartnerProfile::count(), 'pending_partners' => PartnerProfile::where('verification_status', 'pending')->count(), 'assets' => Asset::whereNotIn('status', ['cart', 'bulk_draft'])->count(), 'active_requests' => HandoverRequest::whereNotIn('status', HandoverRequest::TERMINAL_STATUSES)->count(), 'open_issues' => IssueReport::whereIn('status', ['open', 'in_review'])->count()], 'impact' => app(ImpactService::class)->summary(), 'recentPartners' => PartnerProfile::with('user')->latest()->limit(6)->get(), 'recentIssues' => IssueReport::with(['reporter', 'asset'])->latest()->limit(6)->get()]);
    }
}
