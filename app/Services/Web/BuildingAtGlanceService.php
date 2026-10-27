<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\ChargeCalculation;
use App\Models\ChargeExpenseAllocation;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitInvoice;
use Illuminate\Database\Eloquent\Builder;

final class BuildingAtGlanceService
{
    public function report(Building $building): array
    {
        $today = now()->toDateString();
        $current = static fn ($query) => $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today));
        $currency = strtoupper($building->currency ?: 'IRR');
        $units = Unit::query()->whereHas('floor.block', fn (Builder $q) => $q->where('building_id', $building->id))
            ->with([
                'floor.block', 'unitOwnerships' => $current, 'unitOwnerships.user:id,first_name,last_name',
                'unitOccupancies' => $current, 'unitOccupancies.user:id,first_name,last_name',
                'wallets' => fn ($q) => $q->where('currency', $currency),
            ])->get()->sortBy(fn (Unit $unit) => [
                $unit->floor->block->sort_order, $unit->floor->block_id,
                $unit->floor->floor_number, str_pad($unit->unit_number, 12, '0', STR_PAD_LEFT),
            ])->values();
        $period = ChargePeriod::query()->where('building_id', $building->id)
            ->whereIn('status', ['calculated', 'issued', 'closed'])
            ->orderByDesc('period_end')->orderByDesc('id')->first();
        $invoiceBase = UnitInvoice::query()->where('building_id', $building->id);
        $debts = (clone $invoiceBase)->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->selectRaw('unit_id, SUM(outstanding_amount) AS amount')->groupBy('unit_id')->pluck('amount', 'unit_id');
        $periodInvoices = collect();
        $calculations = collect();
        $allocations = collect();
        if ($period) {
            $periodInvoices = (clone $invoiceBase)->where('charge_period_id', $period->id)
                ->whereNotIn('status', ['cancelled', 'void'])
                ->selectRaw('unit_id, SUM(total_amount) AS amount')->groupBy('unit_id')->pluck('amount', 'unit_id');
            $calculations = ChargeCalculation::query()->where('charge_period_id', $period->id)
                ->selectRaw('unit_id, SUM(calculated_amount) AS amount')->groupBy('unit_id')->pluck('amount', 'unit_id');
            $allocations = ChargeExpenseAllocation::query()->where('charge_period_id', $period->id)
                ->selectRaw('unit_id, SUM(allocated_amount) AS amount')->groupBy('unit_id')->pluck('amount', 'unit_id');
        }
        $names = static fn ($links) => $links->map(fn ($link) => trim(($link->user?->first_name ?? '').' '.($link->user?->last_name ?? '')) ?: 'کاربر #'.$link->user_id)->unique()->values();
        $rows = $units->map(function (Unit $unit) use ($debts, $periodInvoices, $calculations, $allocations, $period, $names): array {
            $wallet = $unit->wallets->first();
            $balance = (int) ($wallet?->balance ?? 0);
            $locked = (int) ($wallet?->locked_balance ?? 0);
            $available = $wallet?->is_active ? max(0, $wallet->availableBalance()) : 0;
            $debt = (int) ($debts[$unit->id] ?? 0);
            $net = $debt - $available;
            return [
                'unit' => $unit, 'floor' => $unit->floor,
                'owners' => $names($unit->unitOwnerships), 'residents' => $names($unit->unitOccupancies),
                'occupied' => $unit->unitOccupancies->isNotEmpty(), 'people' => (int) $unit->resident_count,
                'balance' => $balance, 'locked' => $locked, 'available' => $available,
                'walletInactive' => $wallet && ! $wallet->is_active,
                'debt' => $debt, 'net' => $net,
                'latest' => $period ? (int) ($periodInvoices[$unit->id] ?? ((int) ($calculations[$unit->id] ?? 0) + (int) ($allocations[$unit->id] ?? 0))) : null,
            ];
        });
        return [
            'rows' => $rows, 'floors' => $rows->groupBy(fn (array $row) => $row['floor']->id),
            'period' => $period, 'currency' => $currency,
            'totals' => [
                'units' => $rows->count(), 'occupied' => $rows->where('occupied', true)->count(),
                'people' => $rows->sum('people'), 'debt' => $rows->sum('debt'),
                'available' => $rows->sum('available'), 'net' => $rows->sum('net'),
            ],
        ];
    }
}
