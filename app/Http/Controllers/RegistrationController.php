<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register', ['pageTitle' => 'Registrasi eksternal']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $plainToken = Str::random(64);
        DB::transaction(function () use ($data, $plainToken): void {
            $userId = DB::table('users')->insertGetId([
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'role' => 'eksternal',
                'status' => 'pending',
            ]);
            DB::table('email_verifications')->insert([
                'user_id' => $userId,
                'token' => hash('sha256', $plainToken),
                'expires_at' => now()->addDay(),
            ]);
        });

        $message = 'Registrasi berhasil. Akun Anda menunggu verifikasi email dan persetujuan pustakawan.';
        if (app()->environment('local')) {
            $message .= ' Tautan pengujian: ' . url('/verify-email?token=' . urlencode($plainToken));
        }

        return redirect('/login')->with('flash', ['type' => 'success', 'message' => $message]);
    }

    public function verify(Request $request)
    {
        $token = (string) $request->query('token', '');
        $verified = false;

        if (preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1) {
            $verification = DB::table('email_verifications')
                ->where('token', hash('sha256', $token))
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->first();
            if ($verification) {
                DB::transaction(function () use ($verification, $token): void {
                    DB::table('email_verifications')
                        ->where('token', hash('sha256', $token))
                        ->update(['verified_at' => now()]);
                    DB::table('users')
                        ->where('id', $verification->user_id)
                        ->update(['status' => 'pending']);
                });
                $verified = true;
            }
        }

        return view('auth.verification', [
            'pageTitle' => $verified ? 'Email terverifikasi' : 'Verifikasi tidak valid',
            'verified' => $verified,
        ]);
    }
}
