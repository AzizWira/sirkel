<?php

use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureVerifiedEmail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'profile.complete' => EnsureProfileComplete::class,
            'email.verified.custom' => EnsureVerifiedEmail::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $safeProtectedRedirect = function (Request $request, string $message) {
            if (!in_array($request->method(), ['GET', 'HEAD'], true) || !$request->user()) {
                return null;
            }

            // Redirect ramah hanya untuk navigasi halaman browser. Request API,
            // AJAX, dan test HTTP biasa tetap menerima status 403/404 asli sehingga
            // kontrak keamanan backend tidak berubah.
            $isBrowserNavigation = strtolower((string) $request->header('Sec-Fetch-Mode')) === 'navigate'
                || strtolower((string) $request->header('Sec-Fetch-Dest')) === 'document';
            if (!$isBrowserNavigation) {
                return null;
            }

            $path = trim($request->path(), '/');
            $protectedArea = $path === 'app' || str_starts_with($path, 'app/')
                || $path === 'partner' || str_starts_with($path, 'partner/')
                || $path === 'admin' || str_starts_with($path, 'admin/');

            if (!$protectedArea) {
                return null;
            }

            $activeRole = $request->user()->activeAccessRole();
            $homeRoute = match ($activeRole) {
                'admin' => 'admin.dashboard',
                'partner' => 'partner.dashboard',
                default => 'user.dashboard',
            };

            // Jika URL lama berasal dari akses/akun yang berbeda, jangan mengirim
            // pengguna ke halaman yang akan ditolak lagi. Kembalikan ke beranda
            // akses yang sedang aktif.
            $prefixMatchesRole = match ($activeRole) {
                'admin' => $path === 'admin' || str_starts_with($path, 'admin/'),
                'partner' => $path === 'partner' || str_starts_with($path, 'partner/'),
                default => $path === 'app' || str_starts_with($path, 'app/'),
            };

            if (!$prefixMatchesRole) {
                return redirect()->route($homeRoute)->with('warning', $message);
            }

            $sectionRoute = match (true) {
                $activeRole === 'user' && str_starts_with($path, 'app/barang') => 'user.assets.index',
                $activeRole === 'partner' && str_starts_with($path, 'partner/requests') => 'partner.requests.index',
                $activeRole === 'partner' && (str_starts_with($path, 'partner/barang') || str_starts_with($path, 'partner/transfers')) => 'partner.assets.index',
                $activeRole === 'admin' && str_starts_with($path, 'admin/mitra') => 'admin.partners.index',
                $activeRole === 'admin' && str_starts_with($path, 'admin/laporan') => 'admin.issues.index',
                $activeRole === 'admin' && str_starts_with($path, 'admin/master-data') => 'admin.master.index',
                default => $homeRoute,
            };

            return redirect()->route($sectionRoute)->with('warning', $message);
        };

        // ID acak, link lama, atau resource yang memang tidak ada di area aplikasi
        // dikembalikan ke menu terdekat agar pengguna tidak melihat halaman error.
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($safeProtectedRedirect) {
            return $safeProtectedRedirect($request, 'Data yang Anda buka tidak ditemukan atau sudah tidak tersedia.');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($safeProtectedRedirect) {
            return $safeProtectedRedirect($request, 'Halaman tersebut tidak tersedia untuk akun yang sedang digunakan.');
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($safeProtectedRedirect) {
            if (!$exception instanceof HttpExceptionInterface) {
                return null;
            }

            $status = $exception->getStatusCode();

            if (in_array($status, [403, 404], true)) {
                $message = $status === 404
                    ? 'Data yang Anda buka tidak ditemukan atau sudah tidak tersedia.'
                    : 'Halaman tersebut tidak tersedia untuk akun yang sedang digunakan.';

                $redirect = $safeProtectedRedirect($request, $message);
                if ($redirect) {
                    return $redirect;
                }
            }

            // Business-rule 422 dari aksi form tidak boleh melempar pengguna ke
            // halaman exception/debug. JSON/AJAX tetap menerima status asli agar
            // klien programatik dan test dapat membaca error dengan benar.
            if ($status !== 422 || $request->expectsJson() || in_array($request->method(), ['GET', 'HEAD'], true)) {
                return null;
            }

            $message = trim($exception->getMessage()) ?: 'Aksi tidak dapat dilakukan karena status data sudah berubah. Muat ulang halaman dan coba kembali.';

            return back()
                ->withInput()
                ->withErrors(['flow' => $message]);
        });
    })->create();
