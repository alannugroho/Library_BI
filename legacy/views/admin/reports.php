<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= legacy_e($pageTitle) ?> | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><header class="site-header"><div class="container navigation"><a class="brand" href="/"><span class="brand-mark">BI</span><span><strong>Digital Library</strong><small>Bank Indonesia Institute</small></span></a><nav class="nav-links"><a href="/dashboard">Dashboard</a><a href="/admin/catalog">Katalog</a></nav></div></header>
<main class="section"><div class="container admin-page"><div class="section-heading"><div><p class="eyebrow">Pustakawan</p><h1 class="page-heading">Laporan</h1></div><a class="button button-outline" href="/dashboard">Kembali</a></div>
<?php if ($flash): ?><div class="notice notice-<?= legacy_e($flash['type']) ?>"><?= legacy_e($flash['message']) ?></div><?php endif; ?>
<div class="report-tabs"><?php foreach (['circulation' => 'Sirkulasi', 'fines' => 'Denda', 'reservations' => 'Reservasi', 'catalog' => 'Katalog'] as $key => $label): ?><a class="button <?= $report === $key ? 'button-primary' : 'button-outline' ?>" href="/admin/reports?report=<?= $key ?>"><?= $label ?></a><?php endforeach; ?><a class="button button-text" href="/admin/reports?report=<?= legacy_e($report) ?>&format=csv">Unduh CSV ↓</a></div>
<?php if ($rows === []): ?><div class="empty-state"><h2>Belum ada data laporan.</h2></div><?php else: ?><div class="report-table-wrap"><table class="report-table"><thead><tr><?php foreach (array_keys($rows[0]) as $heading): ?><th><?= legacy_e(str_replace('_', ' ', $heading)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= legacy_e((string) ($value ?? '-')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div></main></body></html>

