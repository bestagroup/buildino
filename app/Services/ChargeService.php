<?php

namespace App\Services;

use App\Enums\ChargeCalculationType;
use App\Models\ChargeFormula;
use App\Models\ChargeItem;
use App\Models\Unit;
use App\Services\Charge\PeriodResidentCount;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ChargeService
{
    public function calculate(
        ChargeFormula $formula,
        Unit $unit,
        array $context = []
    ): int {
        $configuration = $formula->configuration ?? [];
        $base = (int) (
            $context['base_amount']
            ?? $configuration['base_amount']
            ?? 0
        );

        $type = $formula->calculation_type instanceof ChargeCalculationType
            ? $formula->calculation_type->value
            : $formula->calculation_type;

        return match ($type) {
            'fixed', 'equal' => $base,
            'area' => (int) round($base * (float) ($unit->area ?? 0)),
            'persons' => (int) round($base * (int) ($context['persons'] ?? 0)),
            'floor_coefficient' => (int) round(
                $base
                * (float) (
                    $context['floor_coefficient']
                    ?? 0
                )
            ),
            default => throw ValidationException::withMessages([
                'calculation_type' => 'Unsupported charge calculation type.',
            ]),
        };
    }

    public function calculateBreakdown(
        ChargeFormula $formula,
        Unit $unit,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): array {
        $formula->loadMissing('chargeItems');

        $type = $formula->calculation_type instanceof ChargeCalculationType
            ? $formula->calculation_type
            : ChargeCalculationType::from((string) $formula->calculation_type);

        $baseValue = match ($type) {
            ChargeCalculationType::Area => (float) ($unit->area ?? 0),
            ChargeCalculationType::Persons => $this->activePersons(
                $unit,
                $periodStart,
                $periodEnd
            ),
            ChargeCalculationType::FloorCoefficient =>
                $this->floorCoefficient(
                    $formula,
                    $unit
                ),
            default => 1,
        };

        $items = [];

        foreach ($formula->chargeItems as $item) {
            $amount = $this->calculateItem(
                $type,
                $formula,
                $item,
                $unit,
                $baseValue
            );

            $items[] = [
                'charge_item_id' => $item->getKey(),
                'title' => $item->title,
                'base_amount' => (int) $item->base_amount,
                'base_value' => $baseValue,
                'amount' => $amount,
                'configuration' => $item->configuration,
            ];
        }

        return [
            'amount' => array_sum(array_column($items, 'amount')),
            'base_value' => $baseValue,
            'snapshot' => [
                'formula_id' => $formula->getKey(),
                'formula_title' => $formula->title,
                'calculation_type' => $type->value,
                'formula_configuration' => $formula->configuration,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'items' => $items,
            ],
        ];
    }

    private function calculateItem(
        ChargeCalculationType $type,
        ChargeFormula $formula,
        ChargeItem $item,
        Unit $unit,
        int|float $baseValue
    ): int {
        $baseAmount = (int) $item->base_amount;

        return match ($type) {
            ChargeCalculationType::Fixed,
            ChargeCalculationType::Equal => $baseAmount,

            ChargeCalculationType::Area,
            ChargeCalculationType::Persons,
            ChargeCalculationType::FloorCoefficient =>
                (int) round(
                    $baseAmount * $baseValue
                ),
        };
    }

    private function floorCoefficient(
        ChargeFormula $formula,
        Unit $unit
    ): float {
        $unit->loadMissing('floor');

        $coefficients =
            $formula->configuration[
                'floor_coefficients'
            ] ?? [];

        $floorNumber = (string) (
            $unit->floor?->floor_number
            ?? ''
        );

        return max(
            0,
            (float) (
                $coefficients[$floorNumber]
                ?? 0
            )
        );
    }

    private function activePersons(
        Unit $unit,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        return (new PeriodResidentCount())->count($unit, $periodStart, $periodEnd);
    }
}
