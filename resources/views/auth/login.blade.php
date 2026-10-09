<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $pageTitle }} | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>@include('partials.site-header')<main class="auth-page"><div class="auth-card">
    <p class="eyebrow auth-eyebrow">Akses anggota</p><h1>Selamat datang kembali.</h1><p class="auth-intro">Masuk untuk mengakses koleksi dan layanan perpustakaan.</p>
    @if ($flash)<div class="notice notice-{{ $flash['type'] }}">{{ $flash['message'] }}</div>@endif
    @if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
    <form class="auth-form" method="post" action="/login">
        <input type="hidden" name="csrf_token" value="{{ csrf_token() }}">
        <label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
        <label for="password">Kata sandi</label>
        <div class="password-input">
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="button" class="password-toggle" data-password-toggle data-revealed="false" aria-controls="password" aria-label="Tampilkan kata sandi">
                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
        </div>
        <button class="button button-primary" type="submit">Masuk</button>
    </form>
    <p class="auth-footer">Pengguna eksternal? <a href="/register">Daftar akun</a></p>
</div></main>
<script src="/assets/js/password-toggle.js" defer></script>
</body></html>
