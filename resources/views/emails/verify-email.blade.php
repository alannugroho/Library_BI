<!doctype html>
<html lang="id">
<head><meta charset="utf-8"></head>
<body>
    <h1>Verifikasi email Anda</h1>
    <p>Terima kasih telah mendaftar di Digital Library BI. Klik tombol di bawah untuk mengaktifkan akun Anda.</p>
    <p><a href="{{ $verificationUrl }}">Verifikasi email</a></p>
    <p>Jika tombol di atas tidak berfungsi, salin tautan berikut ke peramban Anda:</p>
    <p>{{ $verificationUrl }}</p>
    <p>Tautan ini berlaku selama 24 jam.</p>
</body>
</html>
