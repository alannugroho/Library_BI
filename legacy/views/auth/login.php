<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><main class="auth-page"><div class="auth-card">
    <a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a>
    <p class="eyebrow auth-eyebrow">Akses anggota</p><h1>Selamat datang kembali.</h1><p class="auth-intro">Masuk untuk mengakses koleksi dan layanan perpustakaan.</p>
    <?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
    <form class="auth-form" method="post" action="/login">
        <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
        <label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email">
        <label for="password">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password">
        <button class="button button-primary" type="submit">Masuk</button>
    </form>
    <p class="auth-footer">Pengguna eksternal? <a href="/register">Daftar akun</a></p>
</div></main></body></html>

