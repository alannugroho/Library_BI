<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><main class="auth-page"><div class="auth-card">
    <a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a>
    <p class="eyebrow auth-eyebrow">Pengguna eksternal</p><h1>Buat akun perpustakaan.</h1><p class="auth-intro">Akun eksternal perlu verifikasi email dan persetujuan pustakawan sebelum dapat mengakses koleksi digital.</p>
    <?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
    <form class="auth-form" method="post" action="/register">
        <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
        <label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email">
        <label for="password">Kata sandi</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
        <label for="password_confirmation">Konfirmasi kata sandi</label><input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
        <button class="button button-primary" type="submit">Daftar akun</button>
    </form>
    <p class="auth-footer">Sudah punya akun? <a href="/login">Masuk</a></p>
</div></main></body></html>

