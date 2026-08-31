<?php

namespace App\Http\Controllers;

use App\Models\PartnerProfile;
use App\Services\ImpactService;

class HomeController extends Controller
{
    public function index()
    {
        return view('public.home', [
            'partners' => PartnerProfile::query()
                ->where('verification_status', 'approved')
                ->where('admin_status', 'active')
                ->where('accepting_requests', true)
                ->limit(6)
                ->get(),
            'impact' => app(ImpactService::class)->summary(),
        ]);
    }

    public function partners()
    {
        return view('public.partners', [
            'partners' => PartnerProfile::with('capabilities')
                ->where('verification_status', 'approved')
                ->where('admin_status', 'active')
                ->orderBy('business_name')
                ->paginate(12),
        ]);
    }

    public function education()
    {
        return view('public.education');
    }

    public function sitemap()
    {
        return response()->view('public.sitemap', [
            'urls' => [
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => route('public.partners'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['loc' => route('public.education'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ],
        ], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
