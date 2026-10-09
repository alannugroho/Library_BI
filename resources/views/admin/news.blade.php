<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kelola news | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head><body>@include('partials.site-header')<main class="section"><div class="container admin-page"><a href="/dashboard">Dashboard</a><h1 class="page-heading">Klipping berita</h1>
@if (session('flash'))<div class="notice notice-{{ session('flash.type') }}">{{ session('flash.message') }}</div>@endif
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
<section class="admin-panel"><p class="eyebrow">{{ $edit ? 'Ubah klipping' : 'Tambah klipping' }}</p>
<form class="auth-form" method="post" action="/admin/news">
@csrf
@if ($edit)<input type="hidden" name="news_id" value="{{ $edit->id }}">@endif
<label for="news-title">Judul berita</label><input id="news-title" name="title" value="{{ old('title', $edit->title ?? '') }}" required>
<label for="news-source">Media sumber</label><input id="news-source" name="source_media" value="{{ old('source_media', $edit->source_media ?? '') }}" required>
<label for="news-date">Tanggal terbit</label><input id="news-date" name="publish_date" type="date" value="{{ old('publish_date', $edit->publish_date ?? '') }}" required>
<label for="news-url">Tautan berita</label><input id="news-url" name="url_link" type="url" value="{{ old('url_link', $edit->url_link ?? '') }}" placeholder="https://contoh.com/berita" required>
<div class="row-actions"><button class="button button-primary" type="submit">{{ $edit ? 'Perbarui klipping' : 'Simpan klipping' }}</button>@if ($edit)<a class="button button-text" href="/admin/news">Batal</a>@endif</div>
</form></section>
<div class="admin-list">@forelse($clippings as $clipping)<div class="admin-list-row"><div><strong>{{ $clipping->title }}</strong><small>{{ $clipping->source_media }} · {{ $clipping->publish_date }}</small></div><div class="row-actions">@if ($clipping->url_link)<a href="{{ $clipping->url_link }}" target="_blank" rel="noopener noreferrer">Buka berita ↗</a>@endif <a href="/admin/news?edit={{ $clipping->id }}">Edit</a><form method="post" action="/admin/news/delete" data-confirm="Hapus klipping ini?"><input type="hidden" name="news_id" value="{{ $clipping->id }}">@csrf<button class="button button-text danger-text" type="submit">Hapus</button></form></div></div>@empty<div class="empty-state"><h2>Belum ada klipping berita.</h2><p>Klipping yang Anda simpan akan tampil di sini.</p></div>@endforelse</div></div></main>
<script src="/assets/js/confirm-action.js" defer></script>
</body></html>
