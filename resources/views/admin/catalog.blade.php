<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kelola katalog | Digital Library BI</title><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>@include('partials.site-header')
<main class="section"><div class="container admin-page">
    <a href="/dashboard">Dashboard</a><h1 class="page-heading">Kelola katalog</h1>
    <section class="admin-panel">
        <form class="auth-form" method="post" action="/admin/catalog" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="action" value="save">
            @if($edit)<input type="hidden" name="catalog_id" value="{{ $edit->id }}">@endif
            <input name="title" placeholder="Judul" value="{{ $edit->title ?? '' }}" required>
            <input name="author" placeholder="Penulis" value="{{ $edit->author ?? '' }}">
            <input name="publisher" placeholder="Penerbit" value="{{ $edit->publisher ?? '' }}">
            <input name="publication_year" type="number" placeholder="Tahun" value="{{ $edit->publication_year ?? '' }}">
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
    <div class="admin-list">@foreach($books as $book)<div class="admin-list-row"><div><strong>{{ $book->title }}</strong><small>{{ $book->type }} · {{ $book->available_stock }}/{{ $book->item_count }} tersedia</small></div><a href="/admin/catalog?edit={{ $book->id }}">Edit</a><form method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="delete"><input type="hidden" name="catalog_id" value="{{ $book->id }}"><button class="button button-text danger-text">Hapus</button></form></div>@endforeach</div>
    @if($edit && $edit->type === 'physical')<h2>Inventaris fisik</h2><form method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="item"><input type="hidden" name="catalog_id" value="{{ $edit->id }}"><input name="barcode" placeholder="Barcode" required><input name="shelf_location" placeholder="Lokasi rak"><button class="button button-primary">Tambah salinan</button></form>@foreach($items as $item)<div class="inventory-row">{{ $item->barcode }} · {{ $item->status }} @if($item->status === 'available')<form method="post" action="/admin/catalog">@csrf<input type="hidden" name="action" value="item-delete"><input type="hidden" name="item_id" value="{{ $item->id }}"><button>Hapus</button></form>@endif</div>@endforeach @endif
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
