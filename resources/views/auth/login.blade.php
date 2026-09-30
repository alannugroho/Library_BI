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
        <label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password">
        <button class="button button-primary" type="submit">Masuk</button>
    </form>
    <p class="auth-footer">Pengguna eksternal? <a href="/register">Daftar akun</a></p>
</div></main></body></html>
