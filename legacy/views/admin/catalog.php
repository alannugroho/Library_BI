<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>
<header class="site-header"><div class="container navigation">
    <a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a>
    <nav class="nav-links"><a href="/dashboard">Dashboard</a><a href="/catalog">OPAC</a></nav>
</div></header>
<main class="section"><div class="container admin-page">
    <div class="section-heading"><div><p class="eyebrow">Pustakawan</p><h1 class="page-heading">Kelola katalog</h1></div><a class="button button-outline" href="/dashboard">Kembali</a></div>
    <?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
    <div class="admin-grid">
        <section class="admin-panel">
            <p class="eyebrow"><?= $editBook ? 'Edit koleksi' : 'Koleksi baru' ?></p>
            <form class="auth-form" method="post" action="/admin/catalog" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <?php if ($editBook): ?><input type="hidden" name="catalog_id" value="<?= (int) $editBook['id'] ?>"><?php endif; ?>
                <label for="title">Judul</label><input id="title" name="title" required value="<?= legacy_e($editBook['title'] ?? '') ?>">
                <label for="author">Penulis</label><input id="author" name="author" value="<?= legacy_e($editBook['author'] ?? '') ?>">
                <label for="publisher">Penerbit</label><input id="publisher" name="publisher" value="<?= legacy_e($editBook['publisher'] ?? '') ?>">
                <div class="form-two-col"><div><label for="publication_year">Tahun terbit</label><input id="publication_year" name="publication_year" type="number" min="1000" max="<?= (int) date('Y') + 1 ?>" value="<?= legacy_e((string) ($editBook['publication_year'] ?? '')) ?>"></div><div><label for="isbn">ISBN</label><input id="isbn" name="isbn" value="<?= legacy_e($editBook['isbn'] ?? '') ?>"></div></div>
                <label for="udc_classification">Klasifikasi UDC</label><input id="udc_classification" name="udc_classification" value="<?= legacy_e($editBook['udc_classification'] ?? '') ?>">
                <label for="type">Jenis koleksi</label><select id="type" name="type" required><option value="physical" <?= ($editBook['type'] ?? '') === 'physical' ? 'selected' : '' ?>>Fisik</option><option value="digital" <?= ($editBook['type'] ?? '') === 'digital' ? 'selected' : '' ?>>Digital</option></select>
                <label for="digital_file">File PDF <?= $editBook && $editBook['digital_file_path'] ? '(unggah untuk mengganti)' : '' ?></label><input id="digital_file" name="digital_file" type="file" accept="application/pdf">
                <button class="button button-primary" type="submit"><?= $editBook ? 'Simpan perubahan' : 'Tambah koleksi' ?></button>
            </form>
            <?php if ($editBook && $editBook['type'] === 'physical'): ?>
                <div class="admin-divider"></div><p class="eyebrow">Inventaris fisik</p>
                <form class="inline-form" method="post" action="/admin/catalog">
                    <input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><input type="hidden" name="action" value="item"><input type="hidden" name="catalog_id" value="<?= (int) $editBook['id'] ?>">
                    <input name="barcode" placeholder="Barcode" required><input name="shelf_location" placeholder="Lokasi rak"><button class="button button-primary" type="submit">Tambah salinan</button>
                </form>
                <?php foreach ($items as $item): ?><div class="inventory-row"><span><strong><?= legacy_e($item['barcode']) ?></strong><small><?= legacy_e($item['shelf_location'] ?: 'Lokasi belum diisi') ?> · <?= legacy_e($item['status']) ?></small></span><?php if ($item['status'] === 'available'): ?><form method="post" action="/admin/catalog"><input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><input type="hidden" name="action" value="item-delete"><input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>"><button class="button button-text danger-text" type="submit">Hapus</button></form><?php endif; ?></div><?php endforeach; ?>
            <?php endif; ?>
        </section>
        <section><p class="eyebrow">Data katalog</p><div class="admin-list"><?php foreach ($books as $book): ?><article class="admin-list-row"><div><strong><?= legacy_e($book['title']) ?></strong><small><?= legacy_e($book['type']) ?> · <?= (int) $book['available_stock'] ?>/<?= (int) $book['item_count'] ?> tersedia</small></div><div class="row-actions"><a class="button button-text" href="/admin/catalog?edit=<?= (int) $book['id'] ?>">Edit</a><form method="post" action="/admin/catalog"><input type="hidden" name="csrf_token" value="<?= legacy_e(legacy_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="catalog_id" value="<?= (int) $book['id'] ?>"><button class="button button-text danger-text" type="submit">Hapus</button></form></div></article><?php endforeach; ?></div></section>
    </div>
</div></main>
</body></html>

