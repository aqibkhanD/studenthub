<?php

namespace App\Services;

use App\Models\ReferenceSequence;
use Illuminate\Support\Facades\DB;

class ReferenceNumberService
{
    /**
     * Generate and reserve the next reference number for the current year.
     * Uses a DB-level lock to prevent race conditions under concurrent submissions.
     *
     * Format: DIU-{YEAR}-{5-digit-sequence}
     * Example: DIU-2026-00421
     */
    public function generate(): string
    {
        $year = (int) now()->format('Y');

        $sequence = DB::transaction(function () use ($year) {
            // Lock the row for this year exclusively
            $record = ReferenceSequence::lockForUpdate()
                ->firstOrCreate(
                    ['year' => $year],
                    ['last_sequence' => 0]
                );

            $next = $record->last_sequence + 1;
            $record->update(['last_sequence' => $next]);

            return $next;
        });

        return sprintf('DIU-%d-%05d', $year, $sequence);
    }
}
