<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DigitalLending
{
    public const LOAN_DAYS = 7;

    public static function sweepExpired(): void
    {
        $now = Carbon::now();

        $catalogIds = DB::table('digital_loans')
            ->where('status', 'active')
            ->where('due_at', '<', $now)
            ->pluck('catalog_id')
            ->unique()
            ->values();

        if ($catalogIds->isEmpty()) {
            return;
        }

        DB::table('digital_loans')
            ->where('status', 'active')
            ->where('due_at', '<', $now)
            ->update(['status' => 'expired', 'returned_at' => $now]);

        foreach ($catalogIds as $catalogId) {
            self::promoteNext((int) $catalogId);
        }
    }

    public static function activeLoanCount(int $catalogId): int
    {
        return DB::table('digital_loans')
            ->where('catalog_id', $catalogId)
            ->where('status', 'active')
            ->where('due_at', '>=', Carbon::now())
            ->count();
    }

    public static function availableCopies(object $book): int
    {
        return max(0, (int) ($book->digital_copies ?? 0) - self::activeLoanCount((int) $book->id));
    }

    public static function hasActiveLoan(int $userId, int $catalogId): bool
    {
        return DB::table('digital_loans')
            ->where('user_id', $userId)
            ->where('catalog_id', $catalogId)
            ->where('status', 'active')
            ->where('due_at', '>=', Carbon::now())
            ->exists();
    }

    public static function promoteNext(int $catalogId): void
    {
        DB::transaction(function () use ($catalogId): void {
            $book = DB::table('catalog_books')
                ->where('id', $catalogId)
                ->where('type', 'digital')
                ->lockForUpdate()
                ->first();

            if (! $book) {
                return;
            }

            $waiter = DB::table('reservations')
                ->where('catalog_id', $catalogId)
                ->where('status', 'waiting_list')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $waiter) {
                return;
            }

            if (self::activeLoanCount($catalogId) >= (int) $book->digital_copies) {
                return;
            }

            if (self::hasActiveLoan((int) $waiter->user_id, $catalogId)) {
                DB::table('reservations')->where('id', $waiter->id)->update(['status' => 'completed']);

                return;
            }

            $now = Carbon::now();
            DB::table('digital_loans')->insert([
                'user_id' => $waiter->user_id,
                'catalog_id' => $catalogId,
                'borrowed_at' => $now,
                'due_at' => $now->copy()->addDays(self::LOAN_DAYS),
                'status' => 'active',
                'created_at' => $now,
            ]);
            DB::table('reservations')->where('id', $waiter->id)->update(['status' => 'completed']);
        });
    }

    public static function resolvePdfPath(object $book): ?string
    {
        $name = basename((string) ($book->digital_file_path ?? ''));
        if ($name === '') {
            return null;
        }

        $private = 'books/'.$name;
        if (Storage::disk('digital')->exists($private)) {
            return Storage::disk('digital')->path($private);
        }

        $legacy = 'uploads/'.$name;
        if (Storage::disk('public')->exists($legacy)) {
            return Storage::disk('public')->path($legacy);
        }

        return null;
    }
}
