<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"><title>Kelola katalog | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>@include('partials.site-header')
<main class="section"><div class="container admin-page">
    <a href="/dashboard">Dashboard</a><h1 class="page-heading">Kelola katalog</h1>
    @if (session('flash'))<div class="notice notice-{{ session('flash.type') }}">{{ session('flash.message') }}</div>@endif
    @if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
    <section class="admin-panel">
        <form class="auth-form" method="post" action="/admin/catalog" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="action" value="save">
            @if($edit)<input type="hidden" name="catalog_id" value="{{ $edit->id }}">@endif
            <label for="catalog-title">Judul</label>
            <input id="catalog-title" name="title" value="{{ old('title', $edit->title ?? '') }}" required>
            <label for="catalog-author">Penulis</label>
            <input id="catalog-author" name="author" value="{{ old('author', $edit->author ?? '') }}">
            <label for="catalog-publisher">Penerbit</label>
            <input id="catalog-publisher" name="publisher" value="{{ old('publisher', $edit->publisher ?? '') }}">
            <label for="catalog-year">Tahun terbit</label>
            <input id="catalog-year" name="publication_year" type="number" value="{{ old('publication_year', $edit->publication_year ?? '') }}">
            <label for="catalog-type">Jenis koleksi</label>
            <select id="catalog-type" name="type">
                <option value="physical" @selected(old('type', $edit->type ?? 'physical') === 'physical')>Fisik</option>
                <option value="digital" @selected(old('type', $edit->type ?? '') === 'digital')>Digital</option>
            </select>
            <div id="digital-file-field" style="{{ old('type', $edit->type ?? 'physical') === 'digital' ? '' : 'display:none' }}">
                <label for="digital-file">File PDF koleksi digital</label>
                <input id="digital-file" name="digital_file" type="file" accept="application/pdf">
                @error('digital_file')
                    <div class="notice notice-error">{{ $message }}</div>
                @enderror
            </div>
            <button class="button button-primary">Simpan</button>
        </form>
    </section>
    <div class="admin-list">@forelse($books as $book)<div class="admin-list-row"><div><strong>{{ $book->title }}</strong><small>{{ $book->type }} · {{ $book->available_stock }}/{{ $book->item_count }} tersedia</small></div><a href="/admin/catalog?edit={{ $book->id }}">Edit</a><form method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="delete"><input type="hidden" name="catalog_id" value="{{ $book->id }}"><button class="button button-text danger-text">Hapus</button></form></div>@empty<div class="empty-state"><h2>Belum ada koleksi.</h2><p>Tambahkan judul pertama dengan formulir di atas.</p></div>@endforelse</div>
    @if($edit && $edit->type === 'physical')<h2>Inventaris fisik</h2><form class="auth-form" method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="item"><input type="hidden" name="catalog_id" value="{{ $edit->id }}"><label for="inventory-barcode">Barcode</label><input id="inventory-barcode" name="barcode" value="{{ old('barcode') }}" required><label for="inventory-shelf">Lokasi rak</label><input id="inventory-shelf" name="shelf_location" value="{{ old('shelf_location') }}"><button class="button button-primary">Tambah salinan</button></form>@forelse($items as $item)<div class="inventory-row">{{ $item->barcode }} · {{ $item->status }} @if($item->status === 'available')<form method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="item-delete"><input type="hidden" name="item_id" value="{{ $item->id }}"><button>Hapus</button></form>@endif</div>@empty<div class="empty-state"><h2>Belum ada salinan fisik.</h2><p>Tambahkan barcode salinan di atas.</p></div>@endforelse @endif
</div></main>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const typeField = document.getElementById('catalog-type');
        const fileField = document.getElementById('digital-file-field');
        const fileInput = document.getElementById('digital-file');

        const updateFileField = () => {
            const isDigital = typeField.value === 'digital';
            fileField.style.display = isDigital ? '' : 'none';
            fileInput.disabled = !isDigital;
            if (!isDigital) {
                fileInput.value = '';
            }
        };

        typeField.addEventListener('change', updateFileField);
        updateFileField();
    });
</script>
</body></html>
