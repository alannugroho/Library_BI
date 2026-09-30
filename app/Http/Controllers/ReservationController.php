<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReservationController extends Controller
{
    public function store(Request $request, string $mode)
    {
        abort_unless(auth()->user()->role === 'anggota', 403);
        $data = $request->validate(['catalog_id' => ['required', 'integer', 'min:1']]);
        $catalogUrl = '/catalog';
        if ($request->filled('return_query') || $request->filled('return_type')) {
            $catalogUrl .= '?' . http_build_query(array_filter([
                'q' => trim((string) $request->input('return_query')),
                'type' => trim((string) $request->input('return_type')),
            ]));
        }

        try {
            DB::transaction(function () use ($data, $mode): void {
                $catalog = DB::table('catalog_books')->where('id', $data['catalog_id'])->lockForUpdate()->first();
                if (!$catalog || $catalog->type !== 'physical') {
                    throw new \RuntimeException('Koleksi ini tidak dapat dipesan secara fisik.');
                }
                $activeCount = DB::table('reservations')
                    ->where('user_id', auth()->id())
                    ->whereIn('status', ['pending_pickup', 'waiting_list'])
                    ->count();
                if ($activeCount >= 3) {
                    throw new \RuntimeException('Batas reservasi aktif Anda sudah mencapai 3 buku.');
                }
                if (DB::table('reservations')->where('user_id', auth()->id())->where('catalog_id', $data['catalog_id'])->whereIn('status', ['pending_pickup', 'waiting_list'])->exists()) {
                    throw new \RuntimeException('Anda sudah memiliki reservasi atau antrean untuk buku ini.');
                }
                $item = DB::table('book_items')->where('catalog_id', $data['catalog_id'])->where('status', 'available')->orderBy('id')->lockForUpdate()->first();
                $waiting = $mode === 'waitlist';
                if ($waiting && $item) {
                    throw new \RuntimeException('Buku sudah tersedia. Silakan gunakan tombol reservasi.');
                }
                if (!$waiting && !$item) {
                    throw new \RuntimeException('Buku sedang tidak tersedia. Gunakan tombol daftar tunggu.');
                }
                DB::table('reservations')->insert([
                    'user_id' => auth()->id(),
                    'catalog_id' => $data['catalog_id'],
                    'reservation_code' => $waiting ? null : 'RSV-' . Str::upper(Str::random(8)),
                    'status' => $waiting ? 'waiting_list' : 'pending_pickup',
                ]);
                if ($item) {
                    DB::table('book_items')->where('id', $item->id)->update(['status' => 'reserved']);
                }
            });
            $message = $mode === 'waitlist' ? 'Anda berhasil masuk daftar tunggu buku ini.' : 'Buku berhasil direservasi.';
            return redirect($catalogUrl)->with('flash', ['type' => 'success', 'message' => $message]);
        } catch (\RuntimeException $exception) {
            return redirect($catalogUrl)->with('flash', ['type' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
