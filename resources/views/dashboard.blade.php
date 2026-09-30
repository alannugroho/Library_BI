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
            <a class="button button-outline" href="/admin/reports">Lihat laporan</a>
        </div>
        <div class="section-heading dashboard-heading"><div><p class="eyebrow">Operasional</p><h2>Kelola sirkulasi</h2></div></div>
        <div class="metric-grid">
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->active_loans ?? 0) }}</strong><span>Peminjaman aktif</span></div>
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->overdue_loans ?? 0) }}</strong><span>Terlambat</span></div>
            <div class="metric-card"><strong>{{ (int) ($circulationSummary->returned_today ?? 0) }}</strong><span>Kembali hari ini</span></div>
        </div>
        <form class="circulation-form" method="post" action="/circulation">
            @csrf
            <label for="circulation-action">Tindakan</label><select id="circulation-action" name="action"><option value="borrow">Pinjam</option><option value="return">Kembali</option><option value="extend">Perpanjang</option></select>
            <label for="member-identifier">NIP atau email anggota</label><input id="member-identifier" name="member_identifier" required>
            <label for="barcode">Barcode buku</label><input id="barcode" name="barcode" required>
            <button class="button button-primary">Proses transaksi</button>
        </form>
        <form method="post" action="/admin/maintenance/overdue">@csrf<button class="button button-outline">Perbarui status overdue</button></form>
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
