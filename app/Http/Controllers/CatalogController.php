<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $books = DB::table('catalog_books as cb')
            ->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')
            ->when($query !== '', fn ($q) => $q->where(fn ($nested) => $nested
                ->where('cb.title', 'like', "%$query%")
                ->orWhere('cb.author', 'like', "%$query%")
                ->orWhere('cb.publisher', 'like', "%$query%")))
            ->when(in_array($type, ['physical', 'digital'], true), fn ($q) => $q->where('cb.type', $type))
            ->select('cb.id', 'cb.title', 'cb.author', 'cb.publication_year', 'cb.type', 'cb.digital_file_path')
            ->selectRaw("COUNT(CASE WHEN bi.status = 'available' THEN 1 END) AS available_stock")
            ->groupBy('cb.id', 'cb.title', 'cb.author', 'cb.publication_year', 'cb.type', 'cb.digital_file_path')
            ->orderBy('cb.title')
            ->limit(100)
            ->get()
            ->map(static fn ($book): array => (array) $book)
            ->all();
        $userReservations = auth()->check()
            ? DB::table('reservations')
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending_pickup', 'waiting_list'])
                ->get(['catalog_id', 'reservation_code', 'status'])
                ->keyBy('catalog_id')
                ->map(static fn ($reservation): array => (array) $reservation)
                ->all()
            : [];

        return view('catalog', [
            'pageTitle' => $query !== '' ? "Hasil pencarian: $query" : ($type === 'digital' ? 'Koleksi digital' : 'Katalog koleksi'),
            'query' => $query,
            'type' => $type,
            'books' => $books,
            'databaseError' => null,
            'flash' => session('flash'),
            'userReservations' => $userReservations,
        ]);
    }
}
