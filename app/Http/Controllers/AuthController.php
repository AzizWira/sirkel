<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\{EmailOtpCode, User};
use App\Notifications\SirkelMailNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Password, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }
    public function registerForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:190|unique:users',
            'password' => 'required|min:8|confirmed',
            'whatsapp' => 'required|string|max:24',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'whatsapp' => $this->phone($data['whatsapp']),
            'role' => UserRole::USER,
        ]);

        Auth::login($user);
        $request->session()->put('active_role', 'user');
        $this->sendOtp($user);

        return redirect()->route('otp.form');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required']);
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        return $this->afterAuthentication($request, $request->user());
    }

    public function accessForm(Request $request)
    {
        $user = $request->user()->loadMissing('partnerProfile');
        if (!$user->email_verified_at)
            return redirect()->route('otp.form');
        if (!$user->profile_completed_at)
            return redirect()->route('profile.complete');

        $roles = $user->availableAccessRoles();
        if (count($roles) <= 1) {
            $role = $roles[0] ?? 'user';
            $request->session()->put('active_role', $role);
            return redirect($this->homeForRole($role));
        }

        return view('auth.choose-access', ['roles' => $roles]);
    }

    public function chooseAccess(Request $request)
    {
        $data = $request->validate(['access' => 'required|in:user,partner,admin']);
        $user = $request->user()->loadMissing('partnerProfile');

        if (!in_array($data['access'], $user->availableAccessRoles(), true)) {
            throw ValidationException::withMessages([
                'access' => 'Akses tersebut belum tersedia untuk akun Anda.',
            ]);
        }

        $request->session()->put('active_role', $data['access']);
        $request->session()->regenerate();

        return redirect($this->homeForRole($data['access']));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function otpForm()
    {
        return view('auth.otp');
    }

    public function resendOtp(Request $request)
    {
        $key = 'otp:' . $request->user()->id . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['otp' => 'Terlalu banyak permintaan OTP. Coba lagi nanti.']);
        }
        RateLimiter::hit($key, 3600);
        $this->sendOtp($request->user());
        return back()->with('success', 'OTP baru telah dikirim.');
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate(['otp' => 'required|digits:6']);
        $otp = EmailOtpCode::where('user_id', $request->user()->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || !Hash::check($data['otp'], $otp->code_hash)) {
            if ($otp)
                $otp->increment('attempts');
            return back()->withErrors(['otp' => 'Kode OTP salah atau sudah kedaluwarsa.']);
        }

        $otp->update(['verified_at' => now()]);
        $request->user()->update(['email_verified_at' => now()]);
        $request->session()->put('active_role', 'user');
        return redirect()->route('profile.complete');
    }

    public function googleRedirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google gagal. Silakan coba lagi.']);
        }

        $email = strtolower((string) $google->getEmail());
        $user = User::where('email', $email)->first();
        if ($user) {
            if ($user->google_id && $user->google_id !== $google->getId()) {
                return redirect()->route('login')->withErrors(['email' => 'Email ini sudah terhubung dengan akun Google lain.']);
            }
            $user->update([
                'google_id' => $user->google_id ?: $google->getId(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
        } else {
            $user = User::create([
                'name' => $google->getName() ?: 'Pengguna SIRKEL',
                'email' => $email,
                'google_id' => $google->getId(),
                'email_verified_at' => now(),
                'role' => UserRole::USER,
            ]);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        if (!$user->profile_completed_at) {
            request()->session()->put('active_role', 'user');
            return redirect()->route('profile.complete');
        }

        return $this->afterAuthentication(request(), $user);
    }

    public function forgotForm()
    {
        return view('auth.forgot-password');
    }

    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $key = 'forgot:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }
        RateLimiter::hit($key, 3600);
        $status = Password::sendResetLink($request->only('email'));
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with('success', 'Jika email tersebut terdaftar, tautan reset password akan dikirim. Silakan periksa kotak masuk dan folder spam.');
        }
        return back()->with('warning', __($status));
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password berhasil diubah.')
            : back()->withErrors(['email' => __($status)]);
    }

    private function afterAuthentication(Request $request, User $user)
    {
        if (!$user->email_verified_at)
            return redirect()->route('otp.form');
        if (!$user->profile_completed_at)
            return redirect()->route('profile.complete');

        $user->loadMissing('partnerProfile');
        $roles = $user->availableAccessRoles();
        if (count($roles) > 1) {
            $request->session()->forget('active_role');
            return redirect()->route('access.choose');
        }

        $role = $roles[0] ?? 'user';
        $request->session()->put('active_role', $role);
        return redirect()->intended($this->homeForRole($role));
    }

    private function homeForRole(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.dashboard'),
            'partner' => route('partner.dashboard'),
            default => route('user.dashboard'),
        };
    }

    private function sendOtp(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        EmailOtpCode::where('user_id', $user->id)->whereNull('verified_at')->update(['expires_at' => now()]);
        EmailOtpCode::create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ]);
        $user->notifyNow(new SirkelMailNotification(
            'Kode OTP SIRKEL',
            'Kode verifikasi email Anda: ' . $code . '. Berlaku 10 menit.'
        ));
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
}
