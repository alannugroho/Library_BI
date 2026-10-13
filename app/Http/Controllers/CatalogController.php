<?php

namespace App\Http\Controllers;

use App\Support\DigitalLending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CatalogController extends Controller
{
    public function index(Request $request)
    {
        DigitalLending::sweepExpired();

        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $genre = trim((string) $request->query('genre', ''));
        $books = DB::table('catalog_books as cb')
            ->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')
            ->when($query !== '', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('cb.title', 'like', "%$query%")
                ->orWhere('cb.author', 'like', "%$query%")
                ->orWhere('cb.publisher', 'like', "%$query%")
                ->orWhere('cb.genre', 'like', "%$query%")))
            ->when(in_array($type, ['physical', 'digital'], true), fn ($q) => $q->where('cb.type', $type))
            ->when($genre !== '', fn ($q) => $q->where('cb.genre', $genre))
            ->select('cb.id', 'cb.title', 'cb.author', 'cb.publication_year', 'cb.type', 'cb.collection_type', 'cb.genre', 'cb.access_mode', 'cb.digital_file_path', 'cb.digital_copies')
            ->selectRaw("COUNT(CASE WHEN bi.status = 'available' THEN 1 END) AS available_stock")
            ->groupBy('cb.id', 'cb.title', 'cb.author', 'cb.publication_year', 'cb.type', 'cb.collection_type', 'cb.genre', 'cb.access_mode', 'cb.digital_file_path', 'cb.digital_copies')
            ->orderBy('cb.title')
            ->limit(100)
            ->get();

        $digitalIds = $books->where('type', 'digital')->pluck('id')->all();
        $activeDigitalCounts = DB::table('digital_loans')
            ->whereIn('catalog_id', $digitalIds)
            ->where('status', 'active')
            ->where('due_at', '>=', now())
            ->select('catalog_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('catalog_id')
            ->pluck('total', 'catalog_id')
            ->all();
        $books = $books->map(static function ($book) use ($activeDigitalCounts): array {
            $row = (array) $book;
            $row['digital_available'] = max(0, (int) $book->digital_copies - (int) ($activeDigitalCounts[$book->id] ?? 0));

            return $row;
        })->all();

        $userReservations = auth()->check()
            ? DB::table('reservations')
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending_pickup', 'waiting_list'])
                ->get(['catalog_id', 'reservation_code', 'status'])
                ->keyBy('catalog_id')
                ->map(static fn ($reservation): array => (array) $reservation)
                ->all()
            : [];
        $userDigitalLoans = auth()->check()
            ? DB::table('digital_loans')
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->where('due_at', '>=', now())
                ->pluck('catalog_id')
                ->mapWithKeys(static fn ($catalogId): array => [(int) $catalogId => true])
                ->all()
            : [];

        return view('catalog', [
            'pageTitle' => $query !== '' ? "Hasil pencarian: $query" : ($type === 'digital' ? 'Koleksi digital' : 'Katalog koleksi'),
            'query' => $query,
            'type' => $type,
            'genre' => $genre,
            'genres' => DB::table('catalog_books')->whereNotNull('genre')->where('genre', '!=', '')->distinct()->orderBy('genre')->pluck('genre'),
            'books' => $books,
            'databaseError' => null,
            'flash' => session('flash'),
            'userReservations' => $userReservations,
            'userDigitalLoans' => $userDigitalLoans,
        ]);
    }
}
