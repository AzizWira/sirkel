<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureVerifiedEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->email_verified_at)
            return redirect()->route('otp.form');
        return $next($request);
    }
}
