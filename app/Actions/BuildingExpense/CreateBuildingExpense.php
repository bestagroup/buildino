<?php

namespace App\Actions\BuildingExpense;

use App\Models\BuildingExpense;
use App\Models\User;
use App\Services\Charge\BuildingExpenseRuleService;
use Illuminate\Support\Facades\DB;

class CreateBuildingExpense
{
    public function __construct(
        private readonly BuildingExpenseRuleService $rules
    ) {
    }

    public function execute(
        array $data,
        User $actor
    ): BuildingExpense
    {
        return DB::transaction(function () use ($data, $actor): BuildingExpense {
            $status = $data['status'] ?? 'draft';

            $expense = BuildingExpense::query()->create([
                ...$data,
                'created_by' => $actor->getKey(),
                'approved_by' => in_array($status, ['approved', 'posted'], true)
                    ? $actor->getKey()
                    : null,
                'approved_at' => in_array($status, ['approved', 'posted'], true)
                    ? now()
                    : null,
                'posted_at' => $status === 'posted' ? now() : null,
            ]);

            $this->rules->synchronize($expense);

            return $expense->refresh();
        });
    }
}
