<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->profile_completed_at)
            return redirect()->route('profile.complete')->with('warning', 'Lengkapi profil terlebih dahulu.');
        return $next($request);
    }
}
