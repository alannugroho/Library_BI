<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminCatalogController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->librarian()) return redirect('/dashboard');
        $books = DB::table('catalog_books as cb')->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')
            ->select('cb.id', 'cb.title', 'cb.type')->selectRaw('COUNT(bi.id) item_count, COUNT(CASE WHEN bi.status = "available" THEN 1 END) available_stock')
            ->groupBy('cb.id', 'cb.title', 'cb.type')->orderByDesc('cb.updated_at')->get();
        $edit = $request->integer('edit') ? DB::table('catalog_books')->where('id', $request->integer('edit'))->first() : null;
        $items = $edit ? DB::table('book_items')->where('catalog_id', $edit->id)->orderByDesc('id')->get() : collect();
        return view('admin.catalog', compact('books', 'edit', 'items'));
    }

    public function store(Request $request)
    {
        if (!$this->librarian()) return redirect('/dashboard');
        $action = $request->input('action');
        if ($action === 'item') {
            $data = $request->validate(['catalog_id' => ['required', 'integer'], 'barcode' => ['required', 'string', 'max:100'], 'shelf_location' => ['nullable', 'string', 'max:100']]);
            DB::table('book_items')->insert($data);
            return redirect('/admin/catalog?edit=' . $data['catalog_id']);
        }
        if ($action === 'item-delete') {
            $item = DB::table('book_items')->where('id', $request->integer('item_id'))->where('status', 'available')->first();
            if ($item) DB::table('book_items')->where('id', $item->id)->delete();
            return redirect('/admin/catalog');
        }
        if ($action === 'delete') {
            $id = $request->integer('catalog_id');
            $busy = DB::table('circulations as c')->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')->where('bi.catalog_id', $id)->whereIn('c.status', ['active', 'overdue'])->exists();
            if (!$busy) DB::table('catalog_books')->where('id', $id)->delete();
            return redirect('/admin/catalog');
        }
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'author' => ['nullable', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:255'], 'publication_year' => ['nullable', 'integer', 'between:1000,2100'], 'isbn' => ['nullable', 'string', 'max:50'], 'udc_classification' => ['nullable', 'string', 'max:100'], 'type' => ['required', 'in:physical,digital']]);
        if ($request->hasFile('digital_file')) {
            $file = $request->file('digital_file');
            abort_unless($file->isValid() && $file->getMimeType() === 'application/pdf' && $file->getSize() <= 20 * 1024 * 1024, 422);
            $name = Str::random(32) . '.pdf';
            $file->move(base_path('legacy/storage/uploads'), $name);
            $data['digital_file_path'] = $name;
        }
        $id = $request->integer('catalog_id');
        $id ? DB::table('catalog_books')->where('id', $id)->update($data) : DB::table('catalog_books')->insert($data);
        return redirect('/admin/catalog');
    }
    private function librarian(): bool { return auth()->check() && auth()->user()->role === 'pustakawan'; }
}
