<?php

namespace App\Services\Charge;

use App\Enums\ChargeCalculationType;
use App\Enums\ExpenseAllocationMethod;
use App\Models\BuildingExpense;
use App\Models\BuildingExpenseAllocationRule;
use App\Models\ChargeFormula;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ExpenseAllocationService
{
    /**
     * @return array<int, array{
     *     unit: Unit,
     *     base_value: float,
     *     amount: int
     * }>
     */
    public function allocate(
        BuildingExpense $expense,
        BuildingExpenseAllocationRule $rule,
        ChargePeriod $period,
        Collection $units
    ): array {
        if ($units->isEmpty()) {
            throw ValidationException::withMessages([
                'units' => 'No active units exist for charge allocation.',
            ]);
        }

        $weights = [];

        foreach ($units as $unit) {
            $weights[$unit->getKey()] = $this->weight(
                $rule,
                $unit,
                $period
            );
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw ValidationException::withMessages([
                'allocation' => sprintf(
                    'Allocation rule %d produced zero total weight for expense %d.',
                    $rule->getKey(),
                    $expense->getKey()
                ),
            ]);
        }

        $amount = (int) $expense->amount;

        $raw = [];
        $allocated = 0;

        foreach ($units as $unit) {
            $unitId = $unit->getKey();
            $exact = ($amount * $weights[$unitId]) / $totalWeight;
            $floor = (int) floor($exact);

            $raw[$unitId] = [
                'unit' => $unit,
                'base_value' => (float) $weights[$unitId],
                'amount' => $floor,
                'fraction' => $exact - $floor,
            ];

            $allocated += $floor;
        }

        $remainder = $amount - $allocated;

        /*
         * Distribute rounding remainder deterministically to the
         * largest fractional shares, then by unit id.
         */
        uasort(
            $raw,
            fn (array $a, array $b): int =>
                ($b['fraction'] <=> $a['fraction'])
                ?: ($a['unit']->getKey() <=> $b['unit']->getKey())
        );

        foreach (array_keys($raw) as $unitId) {
            if ($remainder <= 0) {
                break;
            }

            $raw[$unitId]['amount']++;
            $remainder--;
        }

        ksort($raw);

        return array_map(
            fn (array $row): array => [
                'unit' => $row['unit'],
                'base_value' => $row['base_value'],
                'amount' => $row['amount'],
            ],
            $raw
        );
    }

    /**
     * @return array<int, array{
     *     unit: Unit,
     *     base_value: float,
     *     amount: int
     * }>
     */
    public function allocateByFormula(
        BuildingExpense $expense,
        ChargeFormula $formula,
        ChargePeriod $period,
        Collection $units
    ): array {
        if ($units->isEmpty()) {
            throw ValidationException::withMessages([
                'units' => 'No active units exist for charge allocation.',
            ]);
        }

        $type = $formula->calculation_type
            instanceof ChargeCalculationType
                ? $formula->calculation_type
                : ChargeCalculationType::from(
                    (string) $formula->calculation_type
                );

        if ($type === ChargeCalculationType::Fixed) {
            throw ValidationException::withMessages([
                'formula' =>
                    'Fixed formulas do not allocate a building expense amount.',
            ]);
        }

        $weights = [];

        foreach ($units as $unit) {
            $weights[$unit->getKey()] =
                $this->formulaWeight(
                    $formula,
                    $type,
                    $unit,
                    $period
                );
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw ValidationException::withMessages([
                'allocation' => sprintf(
                    'Charge formula %d produced zero total weight for expense %d.',
                    $formula->getKey(),
                    $expense->getKey()
                ),
            ]);
        }

        $amount = (int) $expense->amount;
        $raw = [];
        $allocated = 0;

        foreach ($units as $unit) {
            $unitId = $unit->getKey();
            $exact = ($amount * $weights[$unitId])
                / $totalWeight;
            $floor = (int) floor($exact);

            $raw[$unitId] = [
                'unit' => $unit,
                'base_value' =>
                    (float) $weights[$unitId],
                'amount' => $floor,
                'fraction' => $exact - $floor,
            ];

            $allocated += $floor;
        }

        $remainder = $amount - $allocated;

        uasort(
            $raw,
            fn (array $a, array $b): int =>
                ($b['fraction'] <=> $a['fraction'])
                ?: (
                    $a['unit']->getKey()
                    <=> $b['unit']->getKey()
                )
        );

        foreach (array_keys($raw) as $unitId) {
            if ($remainder <= 0) {
                break;
            }

            $raw[$unitId]['amount']++;
            $remainder--;
        }

        ksort($raw);

        return array_map(
            fn (array $row): array => [
                'unit' => $row['unit'],
                'base_value' => $row['base_value'],
                'amount' => $row['amount'],
            ],
            $raw
        );
    }

    private function formulaWeight(
        ChargeFormula $formula,
        ChargeCalculationType $type,
        Unit $unit,
        ChargePeriod $period
    ): float {
        return match ($type) {
            ChargeCalculationType::Equal => 1,

            ChargeCalculationType::Area =>
                max(
                    0,
                    (float) ($unit->area ?? 0)
                ),

            ChargeCalculationType::Persons =>
                $this->residentCount(
                    $unit,
                    $period
                ),

            ChargeCalculationType::FloorCoefficient =>
                $this->floorCoefficient(
                    $formula,
                    $unit
                ),

            ChargeCalculationType::Fixed => 0,
        };
    }

    private function floorCoefficient(
        ChargeFormula $formula,
        Unit $unit
    ): float {
        $unit->loadMissing('floor');

        $floorNumber = (string) (
            $unit->floor?->floor_number
            ?? ''
        );

        $coefficients =
            $formula->configuration[
                'floor_coefficients'
            ] ?? [];

        return max(
            0,
            (float) (
                $coefficients[$floorNumber]
                ?? 0
            )
        );
    }

    private function weight(
        BuildingExpenseAllocationRule $rule,
        Unit $unit,
        ChargePeriod $period
    ): float {
        return match ($rule->allocation_method) {
            ExpenseAllocationMethod::Equal => 1,

            ExpenseAllocationMethod::Area =>
                max(0, (float) ($unit->area ?? 0)),

            ExpenseAllocationMethod::Persons =>
                $this->residentCount(
                    $unit,
                    $period
                ),

            ExpenseAllocationMethod::Custom =>
                $this->customWeight(
                    $rule,
                    $unit
                ),
        };
    }

    private function residentCount(
        Unit $unit,
        ChargePeriod $period
    ): int {
        /*
         * Historical occupancy is based on date overlap.
         * We deliberately do not require is_active=true because
         * an occupancy that ended during the period is still part
         * of that historical period.
         */
        return UnitOccupancy::query()
            ->where('unit_id', $unit->getKey())
            ->whereDate(
                'starts_at',
                '<=',
                $period->period_end->toDateString()
            )
            ->where(function ($query) use ($period): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate(
                        'ends_at',
                        '>=',
                        $period->period_start->toDateString()
                    );
            })
            ->distinct('user_id')
            ->count('user_id');
    }

    private function customWeight(
        BuildingExpenseAllocationRule $rule,
        Unit $unit
    ): float {
        $weights = $rule->configuration['weights'] ?? [];

        return max(
            0,
            (float) (
                $weights[$unit->getKey()]
                ?? $weights[(string) $unit->getKey()]
                ?? 0
            )
        );
    }
}
