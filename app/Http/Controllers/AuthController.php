<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login', ['pageTitle' => 'Masuk', 'flash' => session('flash')]);
    }

    public function store(Request $request)
    {
        $token = (string) $request->input('_token', $request->input('csrf_token', ''));
        if ($token === '' || ! hash_equals((string) $request->session()->token(), $token)) {
            throw new HttpException(419, 'The page expired, please try again.');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = strtolower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan login. Silakan coba lagi beberapa menit lagi.',
            ]);
        }

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
        }

        if (Auth::user()->status !== 'active') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun belum aktif. Silakan menunggu verifikasi atau persetujuan pustakawan.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $this->syncLegacySession();

        return redirect('/dashboard');
    }

    public function destroy(Request $request)
    {
        $token = (string) $request->input('_token', $request->input('csrf_token', ''));
        if ($token === '' || ! hash_equals((string) $request->session()->token(), $token)) {
            return redirect('/dashboard')->with('flash', [
                'type' => 'error',
                'message' => 'Permintaan logout tidak valid.',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->clearLegacySession();

        return redirect('/');
    }

    private function syncLegacySession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = Auth::user();
        $_SESSION['user'] = [
            'id' => (int) $user->id,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
            'status' => (string) $user->status,
        ];
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    private function clearLegacySession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];
        session_regenerate_id(true);
    }
}
