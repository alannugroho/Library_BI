<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }} | Digital Library BI</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    @include('partials.site-header')
    <main class="section">
        <div class="container">
            <p class="eyebrow">OPAC Digital Library</p>
            <div class="section-heading"><h1 class="page-heading">{{ $pageTitle }}</h1><div class="row-actions"><a class="button button-outline" href="/dashboard">← Dashboard</a><a class="button button-text" href="/">Beranda</a></div></div>
            <form class="catalog-filters" action="/catalog" method="get">
                <input type="search" name="q" value="{{ $query }}" placeholder="Cari judul, penulis, atau penerbit">
                <select name="type" aria-label="Jenis koleksi">
                    <option value="">Semua jenis</option>
                    <option value="physical" @selected($type === 'physical')>Fisik</option>
                    <option value="digital" @selected($type === 'digital')>Digital</option>
                </select>
                <button class="button button-primary" type="submit">Cari</button>
            </form>
            @if ($flash)<div class="notice notice-{{ $flash['type'] }}">{{ $flash['message'] }}</div>@endif
            @if ($databaseError)
                <div class="notice notice-error">{{ $databaseError }}</div>
            @elseif (count($books) === 0)
                <div class="empty-state"><h2>Belum ada koleksi yang cocok.</h2><p>Coba gunakan kata kunci atau filter yang berbeda.</p></div>
            @else
                <div class="catalog-grid">
                    @foreach ($books as $book)
                        <article class="catalog-card">
                            <div class="catalog-type">{{ $book['type'] === 'digital' ? 'DIGITAL' : 'FISIK' }}</div>
                            <h2>{{ $book['title'] }}</h2>
                            <p>{{ $book['author'] ?: 'Penulis belum dicantumkan' }}</p>
                            <div class="catalog-meta">
                                <span>{{ $book['publication_year'] ?: 'Tahun tidak tersedia' }}</span>
                                @if ($book['type'] === 'physical')
                                    <span>{{ (int) $book['available_stock'] }} tersedia</span>
                                @else
                                    <span>Akses terbatas</span>
                                @endif
                            </div>
                            @if ($book['type'] === 'digital' && $book['digital_file_path'] && Auth::check())<a class="catalog-action button button-primary" href="/digital?id={{ (int) $book['id'] }}">Buka PDF</a>@endif
                            @if ($book['type'] === 'physical' && Auth::check() && Auth::user()->role === 'anggota')
                                @php($reservation = $userReservations[(int) $book['id']] ?? null)
                                @if ($reservation)
                                    <div class="catalog-status">
                                        {{ $reservation['status'] === 'waiting_list' ? 'Anda ada di daftar tunggu.' : 'Reservasi aktif: ' . $reservation['reservation_code'] }}
                                    </div>
                                @elseif ((int) $book['available_stock'] > 0)
                                    <form class="catalog-action" method="post" action="/reserve">
                                        @csrf
                                        <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                        <input type="hidden" name="return_query" value="{{ $query }}">
                                        <input type="hidden" name="return_type" value="{{ $type }}">
                                        <button class="button button-primary" type="submit">Reservasi buku</button>
                                    </form>
                                @else
                                    <form class="catalog-action" method="post" action="/waitlist">
                                        @csrf
                                        <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                        <input type="hidden" name="return_query" value="{{ $query }}">
                                        <input type="hidden" name="return_type" value="{{ $type }}">
                                        <button class="button button-outline" type="submit">Daftar tunggu</button>
                                    </form>
                                @endif
                            @elseif ($book['type'] === 'physical')
                                <a class="catalog-status catalog-link" href="/login">Masuk untuk reservasi</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</body>
</html>
