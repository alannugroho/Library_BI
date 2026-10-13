<?php

namespace App\Http\Controllers;

use App\Support\DigitalLending;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DigitalLoanController extends Controller
{
    public function borrow(Request $request)
    {
        $this->ensureBorrower();
        $data = $request->validate(['catalog_id' => ['required', 'integer', 'min:1']]);
        $redirect = $this->catalogUrl($request);

        DigitalLending::sweepExpired();

        try {
            DB::transaction(function () use ($data): void {
                $book = DB::table('catalog_books')->where('id', $data['catalog_id'])->where('type', 'digital')->lockForUpdate()->first();
                if (! $book) {
                    throw new \RuntimeException('Koleksi digital tidak ditemukan.');
                }
                if (DigitalLending::hasActiveLoan((int) auth()->id(), (int) $book->id)) {
                    throw new \RuntimeException('Anda sudah meminjam buku ini.');
                }
                if (DigitalLending::availableCopies($book) <= 0) {
                    throw new \RuntimeException('Stok digital sedang habis. Silakan gunakan tombol Daftar Tunggu.');
                }

                $now = Carbon::now();
                DB::table('digital_loans')->insert([
                    'user_id' => auth()->id(),
                    'catalog_id' => $book->id,
                    'borrowed_at' => $now,
                    'due_at' => $now->copy()->addDays(DigitalLending::LOAN_DAYS),
                    'status' => 'active',
                    'created_at' => $now,
                ]);
            });
        } catch (\RuntimeException $exception) {
            return redirect($redirect)->with('flash', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect($redirect)->with('flash', ['type' => 'success', 'message' => 'Buku digital berhasil dipinjam. Klik "Baca" untuk membukanya.']);
    }

    public function waitlist(Request $request)
    {
        $this->ensureBorrower();
        $data = $request->validate(['catalog_id' => ['required', 'integer', 'min:1']]);
        $redirect = $this->catalogUrl($request);

        DigitalLending::sweepExpired();

        try {
            DB::transaction(function () use ($data): void {
                $book = DB::table('catalog_books')->where('id', $data['catalog_id'])->where('type', 'digital')->lockForUpdate()->first();
                if (! $book) {
                    throw new \RuntimeException('Koleksi digital tidak ditemukan.');
                }
                if (DigitalLending::hasActiveLoan((int) auth()->id(), (int) $book->id)) {
                    throw new \RuntimeException('Anda sedang meminjam buku ini.');
                }
                if (DigitalLending::availableCopies($book) > 0) {
                    throw new \RuntimeException('Stok digital tersedia. Silakan gunakan tombol Pinjam.');
                }
                if (DB::table('reservations')->where('user_id', auth()->id())->where('catalog_id', $book->id)->whereIn('status', ['pending_pickup', 'waiting_list'])->exists()) {
                    throw new \RuntimeException('Anda sudah berada di daftar tunggu buku ini.');
                }

                DB::table('reservations')->insert([
                    'user_id' => auth()->id(),
                    'catalog_id' => $book->id,
                    'reservation_code' => null,
                    'status' => 'waiting_list',
                ]);
            });
        } catch (\RuntimeException $exception) {
            return redirect($redirect)->with('flash', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect($redirect)->with('flash', ['type' => 'success', 'message' => 'Anda berhasil masuk daftar tunggu buku digital ini.']);
    }

    public function return(Request $request)
    {
        abort_unless(auth()->check(), 403);
        $role = (string) auth()->user()->role;
        $isStaff = $role === 'pustakawan';
        abort_unless($isStaff || in_array($role, ['anggota', 'eksternal'], true), 403);

        $data = $request->validate([
            'catalog_id' => ['nullable', 'integer', 'min:1'],
            'loan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        DigitalLending::sweepExpired();
        $back = $isStaff ? '/admin/circulation' : $this->catalogUrl($request);

        try {
            DB::transaction(function () use ($data, $isStaff): void {
                $query = DB::table('digital_loans')->where('status', 'active');
                if ($isStaff && ! empty($data['loan_id'])) {
                    $query->where('id', $data['loan_id']);
                } else {
                    $query->where('user_id', auth()->id())->where('catalog_id', $data['catalog_id'] ?? 0);
                }

                $loan = $query->lockForUpdate()->first();
                if (! $loan) {
                    throw new \RuntimeException('Peminjaman digital aktif tidak ditemukan.');
                }

                DB::table('digital_loans')->where('id', $loan->id)->update([
                    'status' => 'returned',
                    'returned_at' => Carbon::now(),
                ]);
                DigitalLending::promoteNext((int) $loan->catalog_id);
            });
        } catch (\RuntimeException $exception) {
            return redirect($back)->with('flash', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect($back)->with('flash', ['type' => 'success', 'message' => 'Buku digital berhasil dikembalikan.']);
    }

    public function stream(Request $request)
    {
        $book = $this->gatedBook($request);
        $path = DigitalLending::resolvePdfPath($book);
        abort_unless($path !== null, 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="digital-library-'.$book->id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function gatedBook(Request $request): object
    {
        abort_unless(auth()->check(), 403);
        $book = DB::table('catalog_books')->where('id', $request->integer('id'))->where('type', 'digital')->first();
        abort_unless($book, 404);

        $role = (string) auth()->user()->role;
        $allowed = $role === 'pustakawan' || DigitalLending::hasActiveLoan((int) auth()->id(), (int) $book->id);
        abort_unless($allowed, 403);

        return $book;
    }

    private function ensureBorrower(): void
    {
        abort_unless(auth()->check() && in_array(auth()->user()->role, ['anggota', 'eksternal'], true), 403);
    }

    private function catalogUrl(Request $request): string
    {
        $url = '/catalog';
        if ($request->filled('return_query') || $request->filled('return_type')) {
            $url .= '?'.http_build_query(array_filter([
                'q' => trim((string) $request->input('return_query')),
                'type' => trim((string) $request->input('return_type')),
            ]));
        }

        return $url;
    }
}
