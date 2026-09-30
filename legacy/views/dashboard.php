<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><header class="site-header"><div class="container navigation"><a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a><details class="account-menu"><summary class="account-trigger"><span class="account-icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="img"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20c.8-3.4 3-5 6.5-5s5.7 1.6 6.5 5"></path></svg></span><span class="account-label"><strong><?= legacy_e($_SESSION['user']['email']) ?></strong><small><?= legacy_e($_SESSION['user']['role']) ?></small></span><span class="account-chevron" aria-hidden="true">⌄</span></summary><div class="account-dropdown"><a href="/dashboard">Dashboard</a><form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><button type="submit">Keluar</button></form></div></details></div></header>
<main class="section"><div class="container dashboard"><p class="eyebrow"><?= $_SESSION['user']['role'] === 'pustakawan' ? 'Area pustakawan' : 'Area anggota' ?></p><h1 class="page-heading">Dashboard</h1><p>Anda masuk sebagai <strong><?= legacy_e($_SESSION['user']['role']) ?></strong> dengan email <?= legacy_e($_SESSION['user']['email']) ?>.</p>
    <?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
    <?php if ($_SESSION['user']['role'] === 'pustakawan'): ?>
        <div class="admin-shortcut"><a class="button button-primary" href="/admin/catalog">Kelola katalog &amp; inventaris →</a></div>
        <div class="admin-shortcuts"><a class="button button-outline" href="/admin/members">Kelola anggota<?= $pendingMembers > 0 ? ' (' . $pendingMembers . ')' : '' ?></a><a class="button button-outline" href="/admin/proposals">Tinjau usulan<?= $pendingProposals > 0 ? ' (' . $pendingProposals . ')' : '' ?></a></div>
        <div class="admin-shortcuts"><a class="button button-outline" href="/admin/news">Kelola news clippings</a><a class="button button-outline" href="/admin/e-resources">Kelola E-Resources</a></div>
        <div class="admin-shortcuts"><a class="button button-outline" href="/admin/reports">Lihat laporan</a><form method="post" action="/admin/maintenance/overdue"><input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><button class="button button-outline" type="submit">Perbarui status overdue</button></form></div>
        <div class="section-heading dashboard-heading"><div><p class="eyebrow">Operasional</p><h2>Kelola sirkulasi</h2></div></div>
        <div class="metric-grid">
            <div class="metric-card"><strong><?= (int) ($circulationSummary['active_loans'] ?? 0) ?></strong><span>Peminjaman aktif</span></div>
            <div class="metric-card"><strong><?= (int) ($circulationSummary['overdue_loans'] ?? 0) ?></strong><span>Terlambat</span></div>
            <div class="metric-card"><strong><?= (int) ($circulationSummary['returned_today'] ?? 0) ?></strong><span>Kembali hari ini</span></div>
        </div>
        <form class="circulation-form" method="post" action="/circulation">
            <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
            <label for="circulation-action">Tindakan</label>
            <select id="circulation-action" name="action" required>
                <option value="borrow">Pinjam</option>
                <option value="return">Kembali</option>
                <option value="extend">Perpanjang</option>
            </select>
            <label for="member-identifier">NIP atau email anggota</label>
            <input id="member-identifier" name="member_identifier" required>
            <label for="barcode">Barcode buku</label>
            <input id="barcode" name="barcode" required>
            <button class="button button-primary" type="submit">Proses transaksi</button>
        </form>
        <div class="section-heading dashboard-heading"><div><p class="eyebrow">Reservasi &amp; antrean</p><h2>Buku dalam tahap reservasi</h2></div></div>
        <?php if ($activeReservations === []): ?>
            <div class="empty-state"><h2>Belum ada reservasi aktif.</h2><p>Reservasi baru dan daftar tunggu anggota akan tampil di sini.</p></div>
        <?php else: ?>
            <div class="reservation-list">
                <?php foreach ($activeReservations as $reservation): ?>
                    <div class="reservation-row">
                        <div>
                            <strong><?= legacy_e($reservation['title']) ?></strong>
                            <small><?= legacy_e($reservation['email']) ?> · <?= legacy_e(date('d M Y H:i', strtotime($reservation['created_at']))) ?></small>
                        </div>
                        <span class="reservation-badge"><?= $reservation['status'] === 'waiting_list' ? 'Daftar tunggu' : 'Menunggu diambil · ' . legacy_e($reservation['reservation_code'] ?: '-') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($recentCirculations !== []): ?>
            <div class="section-heading dashboard-heading"><h2>Transaksi terbaru</h2></div>
            <div class="reservation-list">
                <?php foreach ($recentCirculations as $circulation): ?>
                    <div class="reservation-row"><div><strong><?= legacy_e($circulation['title']) ?></strong><small><?= legacy_e($circulation['email']) ?> · <?= legacy_e($circulation['barcode']) ?></small></div><span class="reservation-badge"><?= legacy_e($circulation['status']) ?><?= (float) $circulation['fine_amount'] > 0 ? ' · Rp ' . number_format((float) $circulation['fine_amount'], 0, ',', '.') : '' ?></span></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($_SESSION['user']['role'] === 'anggota'): ?><div class="admin-shortcut"><a class="button button-outline" href="/proposals">Usulkan koleksi baru →</a></div><?php endif; ?>
    <?php endif; ?>
    <div class="section-heading dashboard-heading"><h2>Reservasi saya</h2><a class="button button-text" href="/catalog">Cari koleksi →</a></div>
    <?php if ($reservations === []): ?>
        <div class="empty-state"><h2>Belum ada reservasi.</h2><p>Telusuri katalog untuk menemukan buku yang Anda butuhkan.</p></div>
    <?php else: ?>
        <div class="reservation-list">
            <?php foreach ($reservations as $reservation): ?>
                <div class="reservation-row"><div><strong><?= legacy_e($reservation['title']) ?></strong><small><?= legacy_e(date('d M Y', strtotime($reservation['created_at']))) ?></small></div><span class="reservation-badge"><?= $reservation['status'] === 'waiting_list' ? 'Daftar tunggu' : legacy_e($reservation['reservation_code'] ?: 'Diproses') ?></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div></main>
</body></html>

