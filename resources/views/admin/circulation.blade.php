<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola sirkulasi | Digital Library BI</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
@include('partials.site-header')
<main class="section">
    <div class="container admin-page">
        <a href="/dashboard">← Dashboard</a>
        <h1 class="page-heading">Kelola sirkulasi</h1>
        @if (session('flash'))
            <div class="notice notice-{{ session('flash.type') }}">{{ session('flash.message') }}</div>
        @endif

        <section class="admin-panel">
            <p class="eyebrow">Transaksi manual</p>
            <h2>Proses peminjaman, pengembalian, atau perpanjangan</h2>
            <form class="circulation-form" method="post" action="/circulation">
                @csrf
                <label for="circulation-action">Tindakan</label>
                <select id="circulation-action" name="action">
                    <option value="borrow">Pinjam</option>
                    <option value="return">Kembali</option>
                    <option value="extend">Perpanjang</option>
                </select>
                <label for="member-identifier">NIP atau email anggota</label>
                <input id="member-identifier" name="member_identifier" list="active-members" value="{{ old('member_identifier') }}" placeholder="Ketik NIP atau email" autocomplete="off" required>
                <datalist id="active-members">
                    @foreach ($members as $member)
                        <option value="{{ $member->nip ?: $member->email }}">{{ $member->nip ?: 'Tanpa NIP' }} · {{ $member->email }}</option>
                    @endforeach
                </datalist>
                <label for="barcode">Barcode buku</label>
                <input id="barcode" name="barcode" required>
                <button class="button button-primary" type="submit">Proses transaksi</button>
            </form>
            <form method="post" action="/admin/maintenance/overdue">
                @csrf
                <button class="button button-outline" type="submit">Perbarui status overdue</button>
            </form>
        </section>

        <section>
            <div class="section-heading dashboard-heading">
                <div><p class="eyebrow">Reservasi siap diproses</p><h2>Ubah reservasi menjadi peminjaman</h2></div>
            </div>
            @if ($pendingPickups->isEmpty())
                <div class="empty-state"><h2>Tidak ada reservasi menunggu pengambilan.</h2></div>
            @else
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Buku</th><th>Anggota</th><th>Barcode</th><th>Kode reservasi</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($pendingPickups as $pickup)
                            <tr>
                                <td>{{ $pickup->title }}</td>
                                <td>{{ $pickup->email }}<small>{{ $pickup->nip ?: 'Tanpa NIP' }}</small></td>
                                <td>{{ $pickup->barcode ?: 'Barcode belum tersedia' }}</td>
                                <td>{{ $pickup->reservation_code ?: '-' }}</td>
                                <td>
                                    @if ($pickup->barcode)
                                        <form method="post" action="/circulation">
                                            @csrf
                                            <input type="hidden" name="action" value="borrow">
                                            <input type="hidden" name="member_identifier" value="{{ $pickup->email }}">
                                            <input type="hidden" name="barcode" value="{{ $pickup->barcode }}">
                                            <button class="button button-small button-primary" type="submit">Tandai dipinjam</button>
                                        </form>
                                    @else
                                        <span class="muted-text">Tidak ada eksemplar</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section>
            <div class="section-heading dashboard-heading"><div><p class="eyebrow">Peminjaman berjalan</p><h2>Pengembalian dan perpanjangan</h2></div></div>
            @if ($activeLoans->isEmpty())
                <div class="empty-state"><h2>Tidak ada peminjaman aktif.</h2></div>
            @else
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Buku</th><th>Anggota</th><th>Barcode</th><th>Status</th><th>Jatuh tempo</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($activeLoans as $loan)
                            <tr>
                                <td>{{ $loan->title }}</td>
                                <td>{{ $loan->email }}<small>{{ $loan->nip ?: 'Tanpa NIP' }}</small></td>
                                <td>{{ $loan->barcode }}</td>
                                <td><span class="status-badge status-{{ $loan->status }}">{{ $loan->status === 'overdue' ? 'Terlambat' : 'Dipinjam' }}</span></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($loan->due_date)->format('d M Y') }}</td>
                                <td class="action-group">
                                    <form method="post" action="/circulation">
                                        @csrf
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="member_identifier" value="{{ $loan->email }}">
                                        <input type="hidden" name="barcode" value="{{ $loan->barcode }}">
                                        <button class="button button-small button-outline" type="submit">Kembalikan</button>
                                    </form>
                                    @if ($loan->status === 'active')
                                        <form method="post" action="/circulation">
                                            @csrf
                                            <input type="hidden" name="action" value="extend">
                                            <input type="hidden" name="member_identifier" value="{{ $loan->email }}">
                                            <input type="hidden" name="barcode" value="{{ $loan->barcode }}">
                                            <button class="button button-small button-text" type="submit">Perpanjang</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section>
            <div class="section-heading dashboard-heading"><div><p class="eyebrow">Koleksi digital</p><h2>Peminjaman digital aktif</h2></div></div>
            @if ($digitalLoans->isEmpty())
                <div class="empty-state"><h2>Tidak ada peminjaman digital aktif.</h2></div>
            @else
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Buku</th><th>Peminjam</th><th>Jatuh tempo</th><th>Aksi</th></tr></thead>
                        <tbody>
                        @foreach ($digitalLoans as $loan)
                            <tr>
                                <td>{{ $loan->title }}</td>
                                <td>{{ $loan->email }}<small>{{ $loan->nip ?: 'Tanpa NIP' }}</small></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($loan->due_at)->format('d M Y H:i') }}</td>
                                <td>
                                    <form method="post" action="/digital/return">
                                        @csrf
                                        <input type="hidden" name="loan_id" value="{{ (int) $loan->id }}">
                                        <button class="button button-small button-outline" type="submit">Kembalikan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if ($digitalWaitlist->isNotEmpty())
                <div class="section-heading dashboard-heading"><div><p class="eyebrow">Antrean</p><h2>Daftar tunggu digital</h2></div></div>
                <div class="report-table-wrap">
                    <table class="report-table">
                        <thead><tr><th>Buku</th><th>Menunggu</th><th>Sejak</th></tr></thead>
                        <tbody>
                        @foreach ($digitalWaitlist as $waiter)
                            <tr>
                                <td>{{ $waiter->title }}</td>
                                <td>{{ $waiter->email }}<small>{{ $waiter->nip ?: 'Tanpa NIP' }}</small></td>
                                <td>{{ \Illuminate\Support\Carbon::parse($waiter->created_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</main>
</body>
</html>
