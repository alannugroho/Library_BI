<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Digital Library BI</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
@include('partials.site-header')
<main class="section"><div class="container dashboard">
    <p class="eyebrow">{{ $user->role === 'pustakawan' ? 'Area pustakawan' : 'Area anggota' }}</p>
    <h1 class="page-heading">Dashboard</h1>
    <p>Anda masuk sebagai <strong>{{ $user->role }}</strong> dengan email {{ $user->email }}.</p>
    @if (session('flash'))
        <div class="notice notice-{{ session('flash.type') }}">{{ session('flash.message') }}</div>
    @endif

    @if ($user->role === 'pustakawan')
        <div class="admin-shortcut"><a class="button button-primary" href="/admin/catalog">Kelola katalog &amp; inventaris →</a></div>
        <div class="admin-shortcuts">
            <a class="button button-outline" href="/admin/members">Kelola anggota{{ $pendingMembers > 0 ? " ($pendingMembers)" : '' }}</a>
            <a class="button button-outline" href="/admin/proposals">Tinjau usulan{{ $pendingProposals > 0 ? " ($pendingProposals)" : '' }}</a>
        </div>
        <div class="admin-shortcuts">
            <a class="button button-outline" href="/admin/news">Kelola news clippings</a>
            <a class="button button-outline" href="/admin/e-resources">Kelola E-Resources</a>
        </div>
        <div class="admin-shortcuts">
            <a class="button button-outline" href="/admin/circulation">Kelola sirkulasi</a>
            <a class="button button-outline" href="/admin/reports">Lihat laporan</a>
        </div>
        <div class="section-heading dashboard-heading"><div><p class="eyebrow">Operasional</p><h2>Monitoring sirkulasi</h2></div><a class="button button-primary" href="/admin/circulation">Buka kelola sirkulasi →</a></div>
        <div class="metric-grid">
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->active_loans ?? 0) }}</strong><span>Peminjaman aktif</span></div>
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->overdue_loans ?? 0) }}</strong><span>Terlambat</span></div>
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->returned_today ?? 0) }}</strong><span>Kembali hari ini</span></div>
        </div>
        <div class="section-heading dashboard-heading">
            <div>
                <p class="eyebrow">Inventaris live</p>
                <h2>Status semua buku</h2>
            </div>
            <span class="muted-text">Diperbarui saat halaman dimuat</span>
        </div>
        @if ($bookStatuses->isEmpty())
            <div class="empty-state"><h2>Belum ada koleksi.</h2><p>Tambahkan buku melalui menu kelola katalog.</p></div>
        @else
            <div class="live-status-table-wrapper">
                <table class="live-status-table">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th>Barcode</th>
                            <th>Lokasi</th>
                            <th>Status</th>
                            <th>Detail</th>
                            <th>Jatuh tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookStatuses as $book)
                            @php
                                $statusLabels = [
                                    'available' => 'Tersedia',
                                    'reserved' => 'Direservasi',
                                    'borrowed' => 'Dipinjam',
                                    'overdue' => 'Terlambat',
                                    'digital' => 'Digital',
                                    'lost' => 'Hilang',
                                    'maintenance' => 'Perawatan',
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $book->title }}</strong>
                                    <small>{{ $book->type === 'digital' ? 'Koleksi digital' : 'Koleksi fisik' }}</small>
                                </td>
                                <td>{{ $book->barcode ?: '—' }}</td>
                                <td>{{ $book->shelf_location ?: '—' }}</td>
                                <td><span class="status-badge status-{{ $book->live_status }}">{{ $statusLabels[$book->live_status] ?? ucfirst($book->live_status) }}</span></td>
                                <td>
                                    @if (in_array($book->live_status, ['borrowed', 'overdue'], true))
                                        {{ $book->borrower_email ?: 'Peminjam tidak diketahui' }}
                                    @elseif ($book->live_status === 'reserved')
                                        Menunggu diambil{{ $book->pickup_code ? ' · ' . $book->pickup_code : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $book->due_date ? \Illuminate\Support\Carbon::parse($book->due_date)->format('d M Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="section-heading dashboard-heading"><div><p class="eyebrow">Reservasi &amp; antrean</p><h2>Buku dalam tahap reservasi</h2></div></div>
        @if ($activeReservations->isEmpty())
            <div class="empty-state"><h2>Belum ada reservasi aktif.</h2><p>Reservasi baru dan daftar tunggu anggota akan tampil di sini.</p></div>
        @else
            <div class="reservation-list">@foreach ($activeReservations as $reservation)
                <div class="reservation-row"><div><strong>{{ $reservation->title }}</strong><small>{{ $reservation->email }} · {{ \Illuminate\Support\Carbon::parse($reservation->created_at)->format('d M Y H:i') }}</small></div>
                <span class="reservation-badge">{{ $reservation->status === 'waiting_list' ? 'Daftar tunggu' : 'Menunggu diambil · ' . ($reservation->reservation_code ?: '-') }}</span></div>
            @endforeach</div>
        @endif
        @if ($recentCirculations->isNotEmpty())
            <div class="section-heading dashboard-heading"><h2>Transaksi terbaru</h2></div>
            <div class="reservation-list">@foreach ($recentCirculations as $circulation)
                <div class="reservation-row"><div><strong>{{ $circulation->title }}</strong><small>{{ $circulation->email }} · {{ $circulation->barcode }}</small></div><span class="reservation-badge">{{ $circulation->status }}{{ (float) $circulation->fine_amount > 0 ? ' · Rp ' . number_format((float) $circulation->fine_amount, 0, ',', '.') : '' }}</span></div>
            @endforeach</div>
        @endif
    @endif
    @if ($user->role === 'anggota')
        <div class="admin-shortcut"><a class="button button-outline" href="/proposals">Usulkan koleksi baru →</a></div>
    @endif

    <div class="section-heading dashboard-heading"><h2>Reservasi saya</h2><a class="button button-text" href="/catalog">Cari koleksi →</a></div>
    @if ($reservations->isEmpty())
        <div class="empty-state"><h2>Belum ada reservasi.</h2><p>Telusuri katalog untuk menemukan buku yang Anda butuhkan.</p></div>
    @else
        <div class="reservation-list">@foreach ($reservations as $reservation)
            <div class="reservation-row"><div><strong>{{ $reservation->title }}</strong><small>{{ \Illuminate\Support\Carbon::parse($reservation->created_at)->format('d M Y') }}</small></div>
            <span class="reservation-badge">{{ $reservation->status === 'waiting_list' ? 'Daftar tunggu' : ($reservation->reservation_code ?: 'Diproses') }}</span></div>
        @endforeach</div>
    @endif
</div></main>
</body>
</html>
