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
                <label class="sr-only" for="catalog-q">Cari judul, penulis, atau penerbit</label>
                <input id="catalog-q" type="search" name="q" value="{{ $query }}" placeholder="Cari judul, penulis, atau penerbit">
                <select name="type" aria-label="Jenis koleksi">
                    <option value="">Semua jenis</option>
                    <option value="physical" @selected($type === 'physical')>Fisik</option>
                    <option value="digital" @selected($type === 'digital')>Digital</option>
                </select>
                <select name="genre" aria-label="Genre koleksi">
                    <option value="">Semua genre</option>
                    @foreach ($genres as $availableGenre)
                        <option value="{{ $availableGenre }}" @selected($genre === $availableGenre)>{{ $availableGenre }}</option>
                    @endforeach
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
                            <div class="catalog-type">{{ strtoupper($book['collection_type']) }} · {{ $book['type'] === 'digital' ? 'DIGITAL' : 'FISIK' }}</div>
                            <h2>{{ $book['title'] }}</h2>
                            <p>{{ $book['author'] ?: 'Penulis belum dicantumkan' }}</p>
                            @if ($book['genre'])
                                <p class="catalog-genre">{{ $book['genre'] }}</p>
                            @endif
                            <div class="catalog-meta">
                                <span>{{ $book['publication_year'] ?: 'Tahun tidak tersedia' }}</span>
                                @if ($book['type'] === 'physical')
                                    <span>{{ (int) $book['available_stock'] }} tersedia</span>
                                @else
                                    <span>{{ (int) ($book['digital_available'] ?? 0) }} dari {{ (int) $book['digital_copies'] }} tersedia</span>
                                @endif
                            </div>
                            @if ($book['type'] === 'digital' && $book['digital_file_path'])
                                @php
                                    $role = Auth::check() ? Auth::user()->role : null;
                                    $hasLoan = Auth::check() && isset($userDigitalLoans[(int) $book['id']]);
                                    $digitalAvailable = (int) ($book['digital_available'] ?? 0);
                                    $digitalReservation = $userReservations[(int) $book['id']] ?? null;
                                @endphp
                                @if ($role === null)
                                    <a class="catalog-status catalog-link" href="/login">Masuk untuk meminjam</a>
                                @elseif ($role === 'pustakawan')
                                    <a class="catalog-action button button-primary" href="/digital/stream?id={{ (int) $book['id'] }}#toolbar=0" target="_blank" rel="noopener">Baca</a>
                                @elseif (in_array($role, ['anggota', 'eksternal'], true))
                                    @if ($hasLoan)
                                        <div class="catalog-actions">
                                            <a class="catalog-action button button-primary" href="/digital/stream?id={{ (int) $book['id'] }}#toolbar=0" target="_blank" rel="noopener">Baca</a>
                                            <form class="catalog-action" method="post" action="/digital/return">
                                                @csrf
                                                <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                                <input type="hidden" name="return_query" value="{{ $query }}">
                                                <input type="hidden" name="return_type" value="{{ $type }}">
                                                <input type="hidden" name="return_genre" value="{{ $genre }}">
                                                <button class="button button-outline" type="submit">Kembalikan</button>
                                            </form>
                                        </div>
                                    @elseif ($digitalAvailable > 0)
                                        <form class="catalog-action" method="post" action="/digital/borrow">
                                            @csrf
                                            <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                            <input type="hidden" name="return_query" value="{{ $query }}">
                                            <input type="hidden" name="return_type" value="{{ $type }}">
                                            <input type="hidden" name="return_genre" value="{{ $genre }}">
                                            <button class="button button-primary" type="submit">Pinjam</button>
                                        </form>
                                    @elseif ($digitalReservation)
                                        <div class="catalog-status">
                                            {{ $digitalReservation['status'] === 'waiting_list' ? 'Anda ada di daftar tunggu.' : 'Reservasi aktif: ' . $digitalReservation['reservation_code'] }}
                                        </div>
                                    @else
                                        <form class="catalog-action" method="post" action="/digital/waitlist">
                                            @csrf
                                            <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                            <input type="hidden" name="return_query" value="{{ $query }}">
                                            <input type="hidden" name="return_type" value="{{ $type }}">
                                            <input type="hidden" name="return_genre" value="{{ $genre }}">
                                            <button class="button button-outline" type="submit">Daftar Tunggu</button>
                                        </form>
                                    @endif
                                @endif
                            @endif
                            @if ($book['type'] === 'physical' && $book['access_mode'] === 'borrowable' && Auth::check() && Auth::user()->role === 'anggota')
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
                                        <input type="hidden" name="return_genre" value="{{ $genre }}">
                                        <button class="button button-primary" type="submit">Reservasi buku</button>
                                    </form>
                                @else
                                    <form class="catalog-action" method="post" action="/waitlist">
                                        @csrf
                                        <input type="hidden" name="catalog_id" value="{{ (int) $book['id'] }}">
                                        <input type="hidden" name="return_query" value="{{ $query }}">
                                        <input type="hidden" name="return_type" value="{{ $type }}">
                                        <input type="hidden" name="return_genre" value="{{ $genre }}">
                                        <button class="button button-outline" type="submit">Daftar tunggu</button>
                                    </form>
                                @endif
                            @elseif ($book['type'] === 'physical')
                                <div class="catalog-status">Baca di perpustakaan</div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</body>
</html>
