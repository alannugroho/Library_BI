<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $pageTitle }} | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>@include('partials.site-header')<main class="error-page">
    <p class="eyebrow">Digital Library BI</p>
    <h1>{{ $verified ? 'Email terverifikasi' : 'Tautan tidak valid' }}</h1>
    <p>{{ $verified ? 'Email Anda sudah terverifikasi. Akun tetap menunggu persetujuan pustakawan.' : 'Tautan verifikasi sudah kedaluwarsa atau tidak dapat digunakan.' }}</p>
    <a class="button button-primary" href="/login">Ke halaman masuk</a>
</main></body></html>
