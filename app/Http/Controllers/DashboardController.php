<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $reservations = DB::table('reservations as r')
            ->join('catalog_books as cb', 'cb.id', '=', 'r.catalog_id')
            ->where('r.user_id', $user->id)
            ->select('r.reservation_code', 'r.status', 'r.created_at', 'cb.title')
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get();

        $data = [
            'user' => $user,
            'reservations' => $reservations,
            'pendingMembers' => 0,
            'pendingProposals' => 0,
            'circulationSummary' => null,
            'recentCirculations' => collect(),
            'activeReservations' => collect(),
        ];

        if ($user->role === 'pustakawan') {
            $data['pendingMembers'] = DB::table('users')
                ->where('role', 'eksternal')
                ->where('status', 'pending')
                ->count();
            $data['pendingProposals'] = DB::table('book_proposals')
                ->where('status', 'submitted')
                ->count();
            $data['circulationSummary'] = DB::table('circulations')
                ->selectRaw("COUNT(CASE WHEN status IN ('active', 'overdue') THEN 1 END) AS active_loans")
                ->selectRaw("COUNT(CASE WHEN status = 'overdue' OR (status = 'active' AND due_date < CURDATE()) THEN 1 END) AS overdue_loans")
                ->selectRaw("COUNT(CASE WHEN status = 'returned' AND return_date = CURDATE() THEN 1 END) AS returned_today")
                ->first();
            $data['recentCirculations'] = DB::table('circulations as c')
                ->join('users as u', 'u.id', '=', 'c.user_id')
                ->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')
                ->join('catalog_books as cb', 'cb.id', '=', 'bi.catalog_id')
                ->select('c.status', 'c.fine_amount', 'u.email', 'bi.barcode', 'cb.title')
                ->orderByDesc('c.id')
                ->limit(10)
                ->get();
            $data['activeReservations'] = DB::table('reservations as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->join('catalog_books as cb', 'cb.id', '=', 'r.catalog_id')
                ->whereIn('r.status', ['pending_pickup', 'waiting_list'])
                ->select('r.reservation_code', 'r.status', 'r.created_at', 'u.email', 'cb.title')
                ->orderByRaw("CASE WHEN r.status = 'pending_pickup' THEN 0 ELSE 1 END")
                ->orderBy('r.created_at')
                ->limit(100)
                ->get();
            $data['bookStatuses'] = DB::table('catalog_books as cb')
                ->leftJoin('book_items as bi', 'bi.catalog_id', '=', 'cb.id')
                ->leftJoin('circulations as c', function ($join): void {
                    $join->on('c.book_item_id', '=', 'bi.id')
                        ->whereIn('c.status', ['active', 'overdue']);
                })
                ->leftJoin('users as borrower', 'borrower.id', '=', 'c.user_id')
                ->leftJoin('reservations as r', function ($join): void {
                    $join->on('r.catalog_id', '=', 'cb.id')
                        ->where('r.status', 'pending_pickup');
                })
                ->select(
                    'cb.id as catalog_id',
                    'cb.title',
                    'cb.type',
                    'bi.id as item_id',
                    'bi.barcode',
                    'bi.shelf_location',
                    'bi.status as item_status',
                    'borrower.email as borrower_email',
                    'c.due_date',
                    'r.reservation_code as pickup_code',
                )
                ->selectRaw("
                    CASE
                        WHEN cb.type = 'digital' THEN 'digital'
                        WHEN c.id IS NOT NULL AND c.due_date < CURDATE() THEN 'overdue'
                        WHEN c.id IS NOT NULL THEN 'borrowed'
                        WHEN bi.status = 'reserved' OR r.id IS NOT NULL THEN 'reserved'
                        WHEN bi.status = 'available' THEN 'available'
                        ELSE bi.status
                    END AS live_status
                ")
                ->orderBy('cb.title')
                ->orderBy('bi.barcode')
                ->get();
        }

        return view('dashboard', $data);
    }
}
