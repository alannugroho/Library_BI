<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CirculationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->check() && auth()->user()->role === 'pustakawan', 403);

        $pendingPickups = DB::table('reservations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('catalog_books as cb', 'cb.id', '=', 'r.catalog_id')
            ->leftJoin('book_items as bi', function ($join): void {
                $join->on('bi.catalog_id', '=', 'r.catalog_id')
                    ->where('bi.status', 'reserved');
            })
            ->where('r.status', 'pending_pickup')
            ->select('r.reservation_code', 'r.created_at', 'u.email', 'u.nip', 'cb.title', 'bi.barcode')
            ->orderBy('r.created_at')
            ->get();

        $activeLoans = DB::table('circulations as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->join('book_items as bi', 'bi.id', '=', 'c.book_item_id')
            ->join('catalog_books as cb', 'cb.id', '=', 'bi.catalog_id')
            ->whereIn('c.status', ['active', 'overdue'])
            ->select('c.status', 'c.due_date', 'u.email', 'u.nip', 'bi.barcode', 'cb.title')
            ->orderBy('c.due_date')
            ->get();

        return view('admin.circulation', compact('pendingPickups', 'activeLoans'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->check() && auth()->user()->role === 'pustakawan', 403);
        $data = $request->validate(['action' => ['required', 'in:borrow,return,extend'], 'member_identifier' => ['required', 'string'], 'barcode' => ['required', 'string']]);
        try {
            DB::transaction(function () use ($data): void {
                $member = DB::table('users')->where(fn ($q) => $q->where('email', $data['member_identifier'])->orWhere('nip', $data['member_identifier']))->where('role', 'anggota')->where('status', 'active')->lockForUpdate()->first();
                if (!$member) throw new \RuntimeException('Anggota tidak ditemukan atau tidak aktif.');
                $item = DB::table('book_items')->where('barcode', $data['barcode'])->lockForUpdate()->first();
                if (!$item) throw new \RuntimeException('Barcode buku tidak ditemukan.');
                if ($data['action'] === 'borrow') {
                    if (!in_array($item->status, ['available', 'reserved'], true)) throw new \RuntimeException('Buku tidak tersedia untuk dipinjam.');
                    $reservation = DB::table('reservations')->where('user_id', $member->id)->where('catalog_id', $item->catalog_id)->where('status', 'pending_pickup')->lockForUpdate()->first();
                    if ($item->status === 'reserved' && !$reservation) throw new \RuntimeException('Buku sedang dicadangkan untuk anggota lain.');
                    DB::table('circulations')->insert(['user_id' => $member->id, 'book_item_id' => $item->id, 'borrow_date' => today(), 'due_date' => today()->addDays(14), 'status' => 'active']);
                    DB::table('book_items')->where('id', $item->id)->update(['status' => 'borrowed']);
                    if ($reservation) DB::table('reservations')->where('id', $reservation->id)->update(['status' => 'completed']);
                } else {
                    $loan = DB::table('circulations')->where('book_item_id', $item->id)->where('user_id', $member->id)->whereIn('status', ['active', 'overdue'])->lockForUpdate()->first();
                    if (!$loan) throw new \RuntimeException('Peminjaman aktif tidak ditemukan.');
                    if ($data['action'] === 'extend') {
                        if ($loan->status !== 'active') throw new \RuntimeException('Peminjaman terlambat tidak dapat diperpanjang.');
                        DB::table('circulations')->where('id', $loan->id)->update(['due_date' => \Illuminate\Support\Carbon::parse($loan->due_date)->addDays(14)]);
                    } else {
                        $fine = max(0, today()->diffInDays(\Illuminate\Support\Carbon::parse($loan->due_date), false) * -1) * 1000;
                        DB::table('circulations')->where('id', $loan->id)->update(['return_date' => today(), 'status' => 'returned', 'fine_amount' => $fine]);
                        DB::table('book_items')->where('id', $item->id)->update(['status' => 'available']);
                        $waiting = DB::table('reservations')->where('catalog_id', $item->catalog_id)->where('status', 'waiting_list')->orderBy('created_at')->lockForUpdate()->first();
                        if ($waiting) { $code = 'RSV-' . strtoupper(bin2hex(random_bytes(4))); DB::table('reservations')->where('id', $waiting->id)->update(['status' => 'pending_pickup', 'reservation_code' => $code]); DB::table('book_items')->where('id', $item->id)->update(['status' => 'reserved']); }
                    }
                }
            });
            return redirect('/admin/circulation')->with('flash', ['type' => 'success', 'message' => 'Transaksi sirkulasi berhasil diproses.']);
        } catch (\RuntimeException $e) {
            return redirect('/admin/circulation')->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function overdue()
    {
        abort_unless(auth()->check() && auth()->user()->role === 'pustakawan', 403);
        $count = DB::table('circulations')->where('status', 'active')->where('due_date', '<', today())->update(['status' => 'overdue']);
        return redirect('/admin/circulation')->with('flash', ['type' => 'success', 'message' => "$count peminjaman ditandai sebagai terlambat."]);
    }
}
