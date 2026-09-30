<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ContentController extends Controller
{
    public function news(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $source = trim((string) $request->query('source', ''));
        $clippings = DB::table('news_clippings')
            ->when($query !== '', fn ($q) => $q->where(fn ($n) => $n->where('title', 'like', "%$query%")->orWhere('source_media', 'like', "%$query%")))
            ->when($source !== '', fn ($q) => $q->where('source_media', $source))
            ->select('id', 'title', 'source_media', 'publish_date')
            ->orderByDesc('publish_date')->orderByDesc('id')->limit(100)->get();
        return view('news', compact('clippings', 'query', 'source'));
    }

    public function newsFile(int $id)
    {
        $clipping = DB::table('news_clippings')->where('id', $id)->first();
        abort_unless($clipping, 404);
        $path = base_path('legacy/storage/uploads/news/' . basename((string) $clipping->file_path));
        abort_unless(is_file($path), 404);
        return response()->file($path);
    }

    public function adminNews()
    {
        if (!$this->librarian()) return redirect('/dashboard');
        return view('admin.news', ['clippings' => DB::table('news_clippings')->orderByDesc('publish_date')->orderByDesc('id')->get()]);
    }

    public function saveNews(Request $request)
    {
        if (!$this->librarian()) return redirect('/dashboard');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source_media' => ['required', 'string', 'max:100'],
            'publish_date' => ['required', 'date'],
            'clipping_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ]);
        $file = $request->file('clipping_file');
        $name = \Illuminate\Support\Str::random(32) . '.' . $file->getClientOriginalExtension();
        $file->move(base_path('legacy/storage/uploads/news'), $name);
        DB::table('news_clippings')->insert(['title' => $data['title'], 'source_media' => $data['source_media'], 'publish_date' => $data['publish_date'], 'file_path' => $name, 'uploaded_by' => auth()->id()]);
        return redirect('/admin/news');
    }

    public function eResources()
    {
        return view('e-resources', ['resources' => DB::table('e_resources')->select('id', 'title', 'description')->orderBy('title')->get()]);
    }

    public function resourceGo(int $id)
    {
        abort_unless(auth()->check() && in_array(auth()->user()->role, ['anggota', 'pustakawan'], true), 403);
        $resource = DB::table('e_resources')->where('id', $id)->first();
        abort_unless($resource && filter_var($resource->url_link, FILTER_VALIDATE_URL) && in_array(parse_url($resource->url_link, PHP_URL_SCHEME), ['http', 'https'], true), 404);
        DB::table('e_resource_activities')->insert(['e_resource_id' => $id, 'user_id' => auth()->id(), 'activity_type' => 'click']);
        return redirect()->away($resource->url_link);
    }

    public function digital(int $id)
    {
        abort_unless(auth()->check(), 403);
        $book = DB::table('catalog_books')->where('id', $id)->where('type', 'digital')->first();
        abort_unless($book, 404);
        $path = base_path('legacy/storage/uploads/' . basename((string) $book->digital_file_path));
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Content-Disposition' => 'inline; filename="digital-library-' . $id . '.pdf"']);
    }

    public function adminResources()
    {
        if (!$this->librarian()) return redirect('/dashboard');
        return view('admin.e-resources', ['resources' => DB::table('e_resources as er')->leftJoin('e_resource_activities as era', function ($join) {
            $join->on('era.e_resource_id', '=', 'er.id')->where('era.activity_type', 'click');
        })->select('er.id', 'er.title', 'er.url_link', 'er.description')->selectRaw('COUNT(era.id) AS click_count')->groupBy('er.id', 'er.title', 'er.url_link', 'er.description')->orderBy('er.title')->get()]);
    }

    public function saveResource(Request $request)
    {
        if (!$this->librarian()) return redirect('/dashboard');
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'url_link' => ['required', 'url:http,https'], 'description' => ['nullable', 'string']]);
        $id = $request->integer('resource_id');
        $id ? DB::table('e_resources')->where('id', $id)->update($data) : DB::table('e_resources')->insert($data);
        return redirect('/admin/e-resources');
    }

    public function deleteResource(Request $request)
    {
        if (!$this->librarian()) return redirect('/dashboard');
        DB::table('e_resources')->where('id', $request->integer('resource_id'))->delete();
        return redirect('/admin/e-resources');
    }

    public function reports(Request $request)
    {
        $this->librarian();
        $report = in_array($request->query('report'), ['circulation', 'fines', 'reservations', 'catalog'], true) ? $request->query('report') : 'circulation';
        $rows = match ($report) {
            'fines' => DB::table('circulations as c')->join('users as u', 'u.id', '=', 'c.user_id')->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')->join('catalog_books as cb', 'cb.id', '=', 'bi.catalog_id')->where('c.fine_amount', '>', 0)->select('u.email', 'cb.title', 'c.due_date', 'c.return_date', 'c.status', 'c.fine_amount')->orderByDesc('c.fine_amount')->limit(500)->get(),
            'reservations' => DB::table('reservations as r')->join('users as u', 'u.id', '=', 'r.user_id')->join('catalog_books as cb', 'cb.id', '=', 'r.catalog_id')->select('u.email', 'cb.title', 'r.reservation_code', 'r.status', 'r.created_at')->orderByDesc('r.created_at')->limit(500)->get(),
            'catalog' => DB::table('catalog_books as cb')->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')->select('cb.title', 'cb.type', 'cb.author', 'cb.publisher', 'cb.publication_year')->selectRaw('COUNT(bi.id) AS item_count, COUNT(CASE WHEN bi.status = "available" THEN 1 END) AS available_stock, COUNT(CASE WHEN bi.status = "borrowed" THEN 1 END) AS borrowed_stock')->groupBy('cb.id', 'cb.title', 'cb.type', 'cb.author', 'cb.publisher', 'cb.publication_year')->limit(500)->get(),
            default => DB::table('circulations as c')->join('users as u', 'u.id', '=', 'c.user_id')->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')->join('catalog_books as cb', 'cb.id', '=', 'bi.catalog_id')->select('c.id', 'u.email', 'cb.title', 'bi.barcode', 'c.borrow_date', 'c.due_date', 'c.return_date', 'c.status', 'c.fine_amount')->orderByDesc('c.id')->limit(500)->get(),
        };
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w'); if ($rows->isNotEmpty()) { fputcsv($out, array_keys((array) $rows->first())); foreach ($rows as $row) fputcsv($out, (array) $row); } fclose($out);
            }, "digital-library-$report.csv", ['Content-Type' => 'text/csv']);
        }
        return view('admin.reports', compact('report', 'rows'));
    }

    private function librarian(): bool { return auth()->check() && auth()->user()->role === 'pustakawan'; }
}
