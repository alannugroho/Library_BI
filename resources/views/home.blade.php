<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal perpustakaan digital Bank Indonesia Institute.">
    <title>Digital Library BI</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
@include('partials.site-header')
<main>
@if ($latestNews->isNotEmpty())
<section class="news-carousel" aria-label="Berita terbaru">
    <div class="container">
        <div class="news-carousel-header">
            <div>
                <p class="eyebrow">Info terbaru</p>
                <h2>Berita &amp; news clippings</h2>
            </div>
            <a class="button button-outline" href="/news">Lihat semua berita →</a>
        </div>
        <div class="news-carousel-window">
            <div class="news-carousel-track">
                @foreach ($latestNews as $index => $news)
                    <article class="news-slide" data-slide="{{ $index }}" aria-hidden="{{ $index < 2 ? 'false' : 'true' }}">
                        <div>
                            <p class="eyebrow">{{ $news->source_media ?: 'News clipping perpustakaan' }}</p>
                            <h3>{{ $news->title }}</h3>
                            <p class="news-slide-date">{{ $news->publish_date ? \Illuminate\Support\Carbon::parse($news->publish_date)->translatedFormat('d F Y') : 'Tanggal tidak tersedia' }}</p>
                        </div>
                        @if ($news->url_link)
                            <a class="button button-primary" href="{{ $news->url_link }}" target="_blank" rel="noopener noreferrer">Baca berita ↗</a>
                        @else
                            <span class="muted-text">Link belum tersedia</span>
                        @endif
                    </article>
                @endforeach
            </div>
            @if ($latestNews->count() > 1)
                <button class="news-carousel-control news-carousel-prev" type="button" data-carousel-action="previous" aria-label="Berita sebelumnya">←</button>
                <button class="news-carousel-control news-carousel-next" type="button" data-carousel-action="next" aria-label="Berita berikutnya">→</button>
            @endif
        </div>
        @if ($latestNews->count() > 1)
            <div class="news-carousel-dots" aria-label="Pilih berita">
                @foreach ($latestNews as $index => $news)
                    <button class="news-carousel-dot {{ $index === 0 ? 'is-active' : '' }}" type="button" data-slide-to="{{ $index }}" aria-label="Tampilkan berita {{ $index + 1 }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
<section class="hero"><div class="container hero-grid"><div>
    <p class="eyebrow">Pusat pengetahuan BINS</p>
    <h1>Temukan pengetahuan. <span>Bangun masa depan.</span></h1>
    <p class="hero-copy">Akses koleksi fisik dan digital, sumber elektronik, serta informasi terbaru perpustakaan dalam satu portal yang aman dan mudah digunakan.</p>
    <div class="hero-actions"><a class="button button-primary" href="#koleksi">Jelajahi koleksi</a><a class="button button-text" href="#layanan">Lihat layanan <span aria-hidden="true">→</span></a></div>
    <div class="hero-stats" aria-label="Ringkasan koleksi"><div><strong>{{ number_format((int) $bookCount, 0, ',', '.') }}</strong><span>Koleksi katalog</span></div><div><strong>{{ number_format((int) $newsCount, 0, ',', '.') }}</strong><span>Klipping berita</span></div><div><strong>Fisik + digital</strong><span>Jenis koleksi</span></div></div>
</div><div class="hero-card" aria-label="Pencarian koleksi"><div class="floating-label">Pencarian cepat</div><div class="search-panel">
    <p class="panel-kicker">OPAC Digital Library</p><h2>Apa yang ingin Anda baca hari ini?</h2>
    <form class="search-form" action="/search" method="get"><label class="sr-only" for="search">Cari judul, penulis, atau topik</label><input id="search" name="q" type="search" placeholder="Judul, penulis, atau topik"><button class="button button-primary" type="submit">Cari</button></form>
    <div class="search-tags"><a href="/catalog?q=Ekonomi">Ekonomi</a><a href="/catalog?q=Moneter">Moneter</a><a href="/catalog?q=Kebijakan">Kebijakan</a></div>
</div></div></div></section>
<section class="section" id="koleksi"><div class="container"><div class="section-heading"><div><p class="eyebrow">Satu pintu pengetahuan</p><h2>Koleksi untuk setiap kebutuhan</h2></div><a class="button button-text" href="/catalog">Lihat semua koleksi <span aria-hidden="true">→</span></a></div>
<div class="collection-grid"><article class="collection-card"><p class="card-number">01</p><h3>Koleksi fisik</h3><p>Telusuri katalog buku dan lakukan reservasi koleksi yang tersedia di perpustakaan.</p><a href="/catalog?type=physical">Telusuri katalog <span>↗</span></a></article><article class="collection-card"><p class="card-number">02</p><h3>Koleksi digital</h3><p>Akses arsip digital dan dokumen pilihan secara aman dari mana saja.</p><a href="/digital-collections">Buka koleksi digital <span>↗</span></a></article><article class="collection-card"><p class="card-number">03</p><h3>E-Resources</h3><p>Temukan jurnal, database, dan sumber elektronik yang mendukung riset Anda.</p><a href="/e-resources">Lihat e-resources <span>↗</span></a></article></div>
</div></section>
<section class="section service-section" id="layanan"><div class="container service-grid"><div><p class="eyebrow">Layanan perpustakaan</p><h2>Semua yang Anda perlukan untuk belajar dan bekerja.</h2></div><div class="service-list"><a href="/reservations"><span>Reservasi buku</span><span>→</span></a><a href="/proposals"><span>Usulan koleksi baru</span><span>→</span></a><a href="/news"><span>News clippings</span><span>→</span></a></div></div></section>
<section class="news-strip" id="berita"><div class="container news-grid"><div><p class="eyebrow">Terbaru dari perpustakaan</p><h2>Berita &amp; news clippings</h2></div><a class="button button-light" href="/news">Baca berita terbaru <span>→</span></a></div></section>
</main>
<footer class="site-footer"><div class="container footer-content"><span>© {{ date('Y') }} Digital Library BI</span><span>Knowledge · Access · Impact</span></div></footer>
@if ($latestNews->count() > 1)
<script src="/assets/js/news-carousel.js?v=3" defer></script>
@endif
</body></html>
