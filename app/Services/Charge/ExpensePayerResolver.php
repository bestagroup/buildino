<?php

namespace App\Services\Charge;

use App\Enums\ExpensePayerResponsibility;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Support\Jalali\JalaliDateFormatter;
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
            $dates = new JalaliDateFormatter();
            $unit->loadMissing('floor.block.building');

            throw ValidationException::withMessages([
                'payer_responsibility' => sprintf(
                    'برای واحد %s (شناسه %d) در ساختمان «%s»، مسئول پرداخت %s در بازهٔ %s تا %s یافت نشد. تاریخ شروع و پایان سابقهٔ سکونت یا مالکیت باید با این دوره هم‌پوشانی داشته باشد؛ وجود ساکن فعلی به‌تنهایی کافی نیست. اگر تاریخ ثبت اطلاعات به‌جای تاریخ واقعی شروع وارد شده، سابقه را اصلاح کنید.',
                    $unit->unit_number,
                    $unit->getKey(),
                    $unit->floor?->block?->building?->title ?? 'نامشخص',
                    $responsibility->label(),
                    $dates->date($period->period_start),
                    $dates->date($period->period_end)
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
