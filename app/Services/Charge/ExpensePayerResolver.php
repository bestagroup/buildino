<?php

namespace App\Services\Charge;

use App\Enums\ExpensePayerResponsibility;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ExpensePayerResolver
{
    public function resolve(
        Unit $unit,
        ExpensePayerResponsibility $responsibility,
        ChargePeriod $period
    ): ?User {
        if ($responsibility === ExpensePayerResponsibility::Unit) {
            return null;
        }

        $payer = $responsibility === ExpensePayerResponsibility::Owner
            ? $this->owner($unit, $period)
            : $this->resident($unit, $period)
                ?? $this->owner($unit, $period);

        if (! $payer) {
            throw ValidationException::withMessages([
                'payer_responsibility' => sprintf(
                    'برای واحد %d هیچ %s معتبری در بازه دوره شارژ یافت نشد.',
                    $unit->getKey(),
                    $responsibility->label()
                ),
            ]);
        }

        return $payer;
    }

    private function owner(Unit $unit, ChargePeriod $period): ?User
    {
        return UnitOwnership::query()
            ->with('user')
            ->where('unit_id', $unit->getKey())
            ->whereDate('starts_at', '<=', $period->period_end->toDateString())
            ->where(function (Builder $query) use ($period): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $period->period_start->toDateString());
            })
            ->orderByDesc('is_primary')
            ->orderByDesc('ownership_percentage')
            ->orderBy('id')
            ->first()
            ?->user;
    }

    private function resident(Unit $unit, ChargePeriod $period): ?User
    {
        return UnitOccupancy::query()
            ->with('user')
            ->where('unit_id', $unit->getKey())
            ->whereDate('starts_at', '<=', $period->period_end->toDateString())
            ->where(function (Builder $query) use ($period): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $period->period_start->toDateString());
            })
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first()
            ?->user;
    }
}
