<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><header class="site-header"><div class="container navigation"><a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a><nav class="nav-links"><a href="/">Beranda</a><a href="/catalog">Katalog</a></nav></div></header>
<main class="section"><div class="container"><div class="section-heading"><div><p class="eyebrow">Sumber penelitian</p><h1 class="page-heading">E-Resources</h1></div><div class="row-actions"><a class="button button-outline" href="/dashboard">← Dashboard</a><a class="button button-outline" href="/login">Masuk</a></div></div>
<?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
<div class="resource-grid"><?php foreach ($resources as $resource): ?><article class="resource-card"><div class="icon-box icon-box-light">◎</div><h2><?= legacy_e($resource['title']) ?></h2><p><?= legacy_e($resource['description'] ?: 'Sumber elektronik pilihan untuk mendukung riset dan pembelajaran.') ?></p><a class="button button-primary" href="/e-resources/go?id=<?= (int) $resource['id'] ?>">Buka resource →</a></article><?php endforeach; ?><?php if ($resources === []): ?><div class="empty-state"><h2>Belum ada E-Resources.</h2></div><?php endif; ?></div>
</div></main></body></html>

