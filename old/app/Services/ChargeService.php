<?php

namespace App\Services;

use App\Enums\ChargeCalculationType;
use App\Models\ChargeFormula;
use App\Models\ChargeItem;
use App\Models\Unit;
use App\Models\UnitOccupancy;
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
            'custom' => (int) (
                $context['calculated_amount']
                ?? throw ValidationException::withMessages([
                    'calculated_amount' => 'Custom calculation amount is required.',
                ])
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
            ChargeCalculationType::Persons => (int) round(
                $baseAmount * $baseValue
            ),

            ChargeCalculationType::Custom => $this->customAmount(
                $formula,
                $item,
                $unit
            ),
        };
    }

    private function customAmount(
        ChargeFormula $formula,
        ChargeItem $item,
        Unit $unit
    ): int {
        $itemMap = $item->configuration['unit_amounts'] ?? [];
        $formulaMap = $formula->configuration['unit_amounts'] ?? [];

        $value = $itemMap[$unit->getKey()]
            ?? $itemMap[(string) $unit->getKey()]
            ?? $formulaMap[$unit->getKey()]
            ?? $formulaMap[(string) $unit->getKey()]
            ?? null;

        if ($value === null) {
            throw ValidationException::withMessages([
                'configuration' => sprintf(
                    'Custom charge amount is missing for unit %d in formula %d.',
                    $unit->getKey(),
                    $formula->getKey()
                ),
            ]);
        }

        return max(0, (int) $value);
    }

    private function activePersons(
        Unit $unit,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): int {
        return UnitOccupancy::query()
            ->where('unit_id', $unit->getKey())
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate(
                        'ends_at',
                        '>=',
                        $periodStart->toDateString()
                    );
            })
            ->distinct('user_id')
            ->count('user_id');
    }
}
