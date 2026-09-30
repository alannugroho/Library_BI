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
        }

        return view('dashboard', $data);
    }
}
