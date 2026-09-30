<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= legacy_e($pageTitle) ?> | Digital Library BI</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header"><div class="container navigation">
        <a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a>
        <nav class="nav-links"><a href="/">Beranda</a><a class="button button-outline" href="/login">Masuk</a></nav>
    </div></header>
    <main class="section">
        <div class="container">
            <p class="eyebrow">OPAC Digital Library</p>
            <div class="section-heading"><h1 class="page-heading"><?= legacy_e($pageTitle) ?></h1><div class="row-actions"><a class="button button-outline" href="/dashboard">← Dashboard</a><a class="button button-text" href="/">Beranda</a></div></div>
            <form class="catalog-filters" action="/catalog" method="get">
                <input type="search" name="q" value="<?= legacy_e($query) ?>" placeholder="Cari judul, penulis, atau penerbit">
                <select name="type" aria-label="Jenis koleksi">
                    <option value="">Semua jenis</option>
                    <option value="physical" <?= $type === 'physical' ? 'selected' : '' ?>>Fisik</option>
                    <option value="digital" <?= $type === 'digital' ? 'selected' : '' ?>>Digital</option>
                </select>
                <button class="button button-primary" type="submit">Cari</button>
            </form>
            <?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
            <?php if ($databaseError): ?>
                <div class="notice notice-error"><?= legacy_e($databaseError) ?></div>
            <?php elseif ($books === []): ?>
                <div class="empty-state"><h2>Belum ada koleksi yang cocok.</h2><p>Coba gunakan kata kunci atau filter yang berbeda.</p></div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($books as $book): ?>
                        <article class="catalog-card">
                            <div class="catalog-type"><?= $book['type'] === 'digital' ? 'DIGITAL' : 'FISIK' ?></div>
                            <h2><?= legacy_e($book['title']) ?></h2>
                            <p><?= legacy_e($book['author'] ?: 'Penulis belum dicantumkan') ?></p>
                            <div class="catalog-meta">
                                <span><?= legacy_e((string) ($book['publication_year'] ?: 'Tahun tidak tersedia')) ?></span>
                                <?php if ($book['type'] === 'physical'): ?>
                                    <span><?= (int) $book['available_stock'] ?> tersedia</span>
                                <?php else: ?>
                                    <span>Akses terbatas</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($book['type'] === 'digital' && $book['digital_file_path'] && isset($_SESSION['user'])): ?><a class="catalog-action button button-primary" href="/digital?id=<?= (int) $book['id'] ?>">Buka PDF</a><?php endif; ?>
                            <?php if ($book['type'] === 'physical' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'anggota'): ?>
                                <?php $reservation = $userReservations[(int) $book['id']] ?? null; ?>
                                <?php if ($reservation): ?>
                                    <div class="catalog-status">
                                        <?= $reservation['status'] === 'waiting_list' ? 'Anda ada di daftar tunggu.' : 'Reservasi aktif: ' . legacy_e($reservation['reservation_code']) ?>
                                    </div>
                                <?php elseif ((int) $book['available_stock'] > 0): ?>
                                    <form class="catalog-action" method="post" action="/reserve">
                                        <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
                                        <input type="hidden" name="catalog_id" value="<?= (int) $book['id'] ?>">
                                        <input type="hidden" name="return_query" value="<?= legacy_e($query) ?>">
                                        <input type="hidden" name="return_type" value="<?= legacy_e($type) ?>">
                                        <button class="button button-primary" type="submit">Reservasi buku</button>
                                    </form>
                                <?php else: ?>
                                    <form class="catalog-action" method="post" action="/waitlist">
                                        <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
                                        <input type="hidden" name="catalog_id" value="<?= (int) $book['id'] ?>">
                                        <input type="hidden" name="return_query" value="<?= legacy_e($query) ?>">
                                        <input type="hidden" name="return_type" value="<?= legacy_e($type) ?>">
                                        <button class="button button-outline" type="submit">Daftar tunggu</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ($book['type'] === 'physical'): ?>
                                <a class="catalog-status catalog-link" href="/login">Masuk untuk reservasi</a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

