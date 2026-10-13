<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdminCatalogController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->librarian()) {
            return redirect('/dashboard');
        }
        $books = DB::table('catalog_books as cb')->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')
            ->select('cb.id', 'cb.title', 'cb.type', 'cb.collection_type', 'cb.genre', 'cb.access_mode', 'cb.digital_copies')->selectRaw('COUNT(bi.id) item_count, COUNT(CASE WHEN bi.status = "available" THEN 1 END) available_stock')
            ->groupBy('cb.id', 'cb.title', 'cb.type', 'cb.collection_type', 'cb.genre', 'cb.access_mode', 'cb.digital_copies')->orderByDesc('cb.updated_at')->get();
        $edit = $request->integer('edit') ? DB::table('catalog_books')->where('id', $request->integer('edit'))->first() : null;
        $items = $edit ? DB::table('book_items')->where('catalog_id', $edit->id)->orderByDesc('id')->get() : collect();

        return view('admin.catalog', compact('books', 'edit', 'items'));
    }

    public function store(Request $request)
    {
        if (! $this->librarian()) {
            return redirect('/dashboard');
        }
        $action = $request->input('action');
        if ($action === 'item') {
            $data = $request->validate(['catalog_id' => ['required', 'integer'], 'barcode' => ['required', 'string', 'max:100'], 'shelf_location' => ['nullable', 'string', 'max:100']]);
            DB::table('book_items')->insert($data);

            return redirect('/admin/catalog?edit='.$data['catalog_id'])->with('flash', ['type' => 'success', 'message' => 'Salinan baru berhasil ditambahkan.']);
        }
        if ($action === 'item-delete') {
            $item = DB::table('book_items')->where('id', $request->integer('item_id'))->where('status', 'available')->first();
            if ($item) {
                DB::table('book_items')->where('id', $item->id)->delete();
            }

            return redirect('/admin/catalog')->with('flash', ['type' => 'success', 'message' => $item ? 'Salinan berhasil dihapus.' : 'Salinan tidak dapat dihapus karena tidak tersedia.']);
        }
        if ($action === 'delete') {
            $id = $request->integer('catalog_id');
            $busy = DB::table('circulations as c')->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')->where('bi.catalog_id', $id)->whereIn('c.status', ['active', 'overdue'])->exists();
            if (! $busy) {
                DB::table('catalog_books')->where('id', $id)->delete();
            }

            return redirect('/admin/catalog')->with('flash', ['type' => 'success', 'message' => $busy ? 'Koleksi tidak dapat dihapus karena masih dipinjam.' : 'Koleksi berhasil dihapus.']);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'between:1000,2100'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'udc_classification' => ['nullable', 'string', 'max:100'],
            'collection_type' => ['required', 'in:book,journal,magazine'],
            'genre' => ['nullable', 'string', 'max:100'],
            'access_mode' => ['required', 'in:borrowable,reading_room'],
            'type' => ['required', 'in:physical,digital'],
            'digital_file' => ['nullable', 'file', 'prohibited_if:type,physical', 'prohibited_unless:collection_type,book', 'mimes:pdf', 'max:20480'],
            'digital_copies' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        if ($data['collection_type'] !== 'book' && ($data['type'] !== 'physical' || $data['access_mode'] !== 'reading_room')) {
            return back()->withErrors(['collection_type' => 'Jurnal dan majalah hanya dapat dibaca di perpustakaan.'])->withInput();
        }
        if ($data['type'] === 'digital' && $data['collection_type'] !== 'book') {
            return back()->withErrors(['collection_type' => 'Jurnal dan majalah tidak tersedia dalam format digital.'])->withInput();
        }
        if ($data['type'] === 'digital' && ! $request->integer('catalog_id') && ! $request->hasFile('digital_file')) {
            return back()->withErrors(['digital_file' => 'File PDF wajib diunggah untuk koleksi digital.'])->withInput();
        }
        if ($request->hasFile('digital_file') && $data['type'] === 'digital') {
            $file = $request->file('digital_file');
            abort_unless($file->isValid() && $file->getMimeType() === 'application/pdf' && $file->getSize() <= 20 * 1024 * 1024, 422);
            $name = Str::random(32).'.pdf';
            Storage::disk('digital')->putFileAs('books', $file, $name);
            $data['digital_file_path'] = $name;
        }
        unset($data['digital_file']);
        if ($data['collection_type'] !== 'book') {
            $data['access_mode'] = 'reading_room';
        }
        if ($data['type'] === 'digital') {
            $data['access_mode'] = 'reading_room';
        }
        $data['digital_copies'] = $data['type'] === 'digital' ? max(1, (int) ($data['digital_copies'] ?? 1)) : 1;
        $id = $request->integer('catalog_id');
        $id ? DB::table('catalog_books')->where('id', $id)->update($data) : DB::table('catalog_books')->insert($data);

        return redirect('/admin/catalog')->with('flash', ['type' => 'success', 'message' => $id ? 'Koleksi berhasil diperbarui.' : 'Koleksi berhasil ditambahkan.']);
    }

    private function librarian(): bool
    {
        return auth()->check() && auth()->user()->role === 'pustakawan';
    }
}
