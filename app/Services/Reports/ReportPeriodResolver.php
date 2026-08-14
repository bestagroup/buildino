<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ReportPeriodResolver
{
    public function resolve(
        ?string $from = null,
        ?string $to = null
    ): array {
        $end = $to
            ? CarbonImmutable::parse($to)->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $start = $from
            ? CarbonImmutable::parse($from)->startOfDay()
            : $end->startOfMonth()->startOfDay();

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'from' =>
                    'Report start date must be before or equal to report end date.',
            ]);
        }

        /*
         * Keep synchronous dashboard queries bounded. Longer historical
         * exports can later use an asynchronous/report-export workflow.
         */
        if ($start->diffInDays($end) > 730) {
            throw ValidationException::withMessages([
                'from' =>
                    'Interactive report range cannot exceed 730 days.',
            ]);
        }

        return [
            'from' => $start,
            'to' => $end,
        ];
    }
}
