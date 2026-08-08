<?php

namespace App\Services;

use App\Models\ChargeFormula;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

class ChargeService
{
    public function calculate(ChargeFormula $formula, Unit $unit, array $context = []): int
    {
        $configuration = $formula->configuration ?? [];
        $base = (int) ($context['base_amount'] ?? $configuration['base_amount'] ?? 0);

        return match ($formula->calculation_type->value ?? $formula->calculation_type) {
            'fixed', 'equal' => $base,
            'area' => (int) round($base * (float) ($unit->area ?? 0)),
            'persons' => (int) round($base * (int) ($context['persons'] ?? 0)),
            'custom' => (int) ($context['calculated_amount'] ?? throw ValidationException::withMessages(['calculated_amount' => 'Custom calculation amount is required.'])),
            default => throw ValidationException::withMessages(['calculation_type' => 'Unsupported charge calculation type.']),
        };
    }
}
