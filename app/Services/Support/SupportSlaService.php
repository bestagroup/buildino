<?php

namespace App\Services\Support;

use App\Enums\SupportPriority;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use Carbon\CarbonImmutable;

final class SupportSlaService
{
    public function deadlines(
        ?int $categoryId,
        SupportPriority|string $priority,
        ?CarbonImmutable $from = null
    ): array {
        $priority = $priority instanceof SupportPriority
            ? $priority
            : SupportPriority::from((string) $priority);

        $policy = SupportSlaPolicy::query()
            ->where('is_active', true)
            ->where('priority', $priority->value)
            ->where(function ($query) use ($categoryId): void {
                if ($categoryId !== null) {
                    $query
                        ->where('support_category_id', $categoryId)
                        ->orWhereNull('support_category_id');
                } else {
                    $query->whereNull('support_category_id');
                }
            })
            ->orderByRaw('support_category_id IS NULL')
            ->first();

        if (! $policy) {
            return [
                'response_due_at' => null,
                'resolution_due_at' => null,
            ];
        }

        $from ??= CarbonImmutable::now();

        return [
            'response_due_at' => $from->addMinutes(
                (int) $policy->first_response_minutes
            ),
            'resolution_due_at' => $from->addMinutes(
                (int) $policy->resolution_minutes
            ),
        ];
    }

    public function refreshResolutionDeadline(SupportTicket $ticket): void
    {
        $deadlines = $this->deadlines(
            $ticket->support_category_id,
            $ticket->priority,
            CarbonImmutable::now()
        );

        $ticket->forceFill([
            'resolution_due_at' => $deadlines['resolution_due_at'],
        ])->save();
    }
}
