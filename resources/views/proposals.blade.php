<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Usulan koleksi | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>@include('partials.site-header')
<main class="section"><div class="container narrow-page"><div class="section-heading"><div><p class="eyebrow">Akuisisi</p><h1 class="page-heading">Usulan koleksi</h1></div><a class="button button-outline" href="/dashboard">Kembali</a></div>
@if (session('flash')) <div class="notice notice-{{ session('flash.type') }}">{{ session('flash.message') }}</div> @endif
@if ($errors->any()) <div class="notice notice-error">{{ $errors->first() }}</div> @endif
<section class="admin-panel"><p class="eyebrow">Kirim usulan baru</p><form class="auth-form" method="post" action="/proposals">@csrf<label for="proposal-title">Judul buku</label><input id="proposal-title" name="title" maxlength="255" required><label for="proposal-author">Penulis</label><input id="proposal-author" name="author" maxlength="255"><label for="proposal-publisher">Penerbit</label><input id="proposal-publisher" name="publisher" maxlength="255"><button class="button button-primary" type="submit">Kirim usulan</button></form></section>
<div class="section-heading dashboard-heading"><h2>Usulan saya</h2></div><div class="admin-list">@forelse ($proposals as $proposal)<div class="admin-list-row"><div><strong>{{ $proposal->title }}</strong><small>{{ $proposal->author ?: 'Penulis belum dicantumkan' }} · {{ $proposal->publisher ?: 'Penerbit belum dicantumkan' }} · {{ \Illuminate\Support\Carbon::parse($proposal->created_at)->format('d M Y') }}</small></div><span class="reservation-badge">{{ $proposal->status }}</span></div>@empty<div class="empty-state"><h2>Belum ada usulan.</h2><p>Usulan koleksi Anda akan muncul di sini.</p></div>@endforelse</div>
</div></main></body></html>
