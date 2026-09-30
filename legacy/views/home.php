<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Portal perpustakaan digital Bank Indonesia Institute.">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container navigation">
            <a class="brand" href="/" aria-label="Digital Library BI">
                <span class="brand-mark">BI</span>
                <span>
                    <strong>Digital Library</strong>
                    <small>Bank Indonesia Institute</small>
                </span>
            </a>
            <nav class="nav-links" aria-label="Navigasi utama">
                <a href="#koleksi">Koleksi</a>
                <a href="#layanan">Layanan</a>
                <a href="#berita">Berita</a>
                <?php if (isset($_SESSION['user'])): ?>
                    <details class="account-menu">
                        <summary class="account-trigger">
                            <span class="account-icon" aria-hidden="true"><svg viewBox="0 0 24 24" role="img"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20c.8-3.4 3-5 6.5-5s5.7 1.6 6.5 5"></path></svg></span>
                            <span class="account-label"><strong><?= legacy_e($_SESSION['user']['email']) ?></strong><small><?= legacy_e($_SESSION['user']['role']) ?></small></span>
                            <span class="account-chevron" aria-hidden="true">⌄</span>
                        </summary>
                        <div class="account-dropdown">
                            <a href="/dashboard">Dashboard</a>
                            <form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><button type="submit">Keluar</button></form>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="button button-outline" href="/login">Masuk</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container hero-grid">
                <div>
                    <p class="eyebrow">Pusat pengetahuan BINS</p>
                    <h1>Temukan pengetahuan. <span>Bangun masa depan.</span></h1>
                    <p class="hero-copy">
                        Akses koleksi fisik dan digital, sumber elektronik, serta informasi terbaru
                        perpustakaan dalam satu portal yang aman dan mudah digunakan.
                    </p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="#koleksi">Jelajahi koleksi</a>
                        <a class="button button-text" href="#layanan">Lihat layanan <span aria-hidden="true">→</span></a>
                    </div>
                    <div class="hero-stats" aria-label="Ringkasan layanan">
                        <div><strong>24/7</strong><span>Akses digital</span></div>
                        <div><strong>OPAC</strong><span>Katalog terpadu</span></div>
                        <div><strong>SSO-ready</strong><span>Siap terintegrasi</span></div>
                    </div>
                </div>
                <div class="hero-card" aria-label="Pencarian koleksi">
                    <div class="card-glow"></div>
                    <div class="floating-label">Pencarian cepat</div>
                    <div class="search-panel">
                        <p class="panel-kicker">OPAC Digital Library</p>
                        <h2>Apa yang ingin Anda baca hari ini?</h2>
                        <form class="search-form" action="/search" method="get">
                            <label class="sr-only" for="search">Cari judul, penulis, atau topik</label>
                            <input id="search" name="q" type="search" placeholder="Judul, penulis, atau topik">
                            <button class="button button-primary" type="submit">Cari</button>
                        </form>
                        <div class="search-tags">
                            <span>Ekonomi</span><span>Moneter</span><span>Kebijakan</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="koleksi">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Satu pintu pengetahuan</p>
                        <h2>Koleksi untuk setiap kebutuhan</h2>
                    </div>
                    <a class="button button-text" href="/catalog">Lihat semua koleksi <span aria-hidden="true">→</span></a>
                </div>
                <div class="collection-grid">
                    <article class="collection-card collection-card-dark">
                        <div class="icon-box">▤</div>
                        <p class="card-number">01</p>
                        <h3>Koleksi fisik</h3>
                        <p>Telusuri katalog buku dan lakukan reservasi koleksi yang tersedia di perpustakaan.</p>
                        <a href="/catalog?type=physical">Telusuri katalog <span aria-hidden="true">↗</span></a>
                    </article>
                    <article class="collection-card">
                        <div class="icon-box icon-box-light">◫</div>
                        <p class="card-number">02</p>
                        <h3>Koleksi digital</h3>
                        <p>Akses arsip digital dan dokumen pilihan secara aman dari mana saja.</p>
                        <a href="/digital-collections">Buka koleksi digital <span aria-hidden="true">↗</span></a>
                    </article>
                    <article class="collection-card">
                        <div class="icon-box icon-box-light">◎</div>
                        <p class="card-number">03</p>
                        <h3>E-Resources</h3>
                        <p>Temukan jurnal, database, dan sumber elektronik yang mendukung riset Anda.</p>
                        <a href="/e-resources">Lihat e-resources <span aria-hidden="true">↗</span></a>
                    </article>
                </div>
            </div>
        </section>

        <section class="section service-section" id="layanan">
            <div class="container service-grid">
                <div>
                    <p class="eyebrow">Layanan perpustakaan</p>
                    <h2>Semua yang Anda perlukan untuk belajar dan bekerja.</h2>
                </div>
                <div class="service-list">
                    <a href="/reservations"><span>Reservasi buku</span><span>→</span></a>
                    <a href="/proposals"><span>Usulan koleksi baru</span><span>→</span></a>
                    <a href="/news"><span>News clippings</span><span>→</span></a>
                </div>
            </div>
        </section>

        <section class="news-strip" id="berita">
            <div class="container news-grid">
                <div>
                    <p class="eyebrow">Terbaru dari perpustakaan</p>
                    <h2>Berita &amp; news clippings</h2>
                </div>
                <a class="button button-light" href="/news">Baca berita terbaru <span aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <span>© <?= date('Y') ?> Digital Library BI</span>
            <span>Knowledge · Access · Impact</span>
        </div>
    </footer>
</body>
</html>

