<?php

namespace App\Actions\BuildingExpense;

use App\Models\BuildingExpense;
use App\Models\Building;
use App\Models\User;
use App\Services\Charge\BuildingExpenseRuleService;
use Illuminate\Support\Facades\DB;
use App\Services\Charge\BuildingBillScheduleService;

class UpdateBuildingExpense
{
    public function __construct(
        private readonly BuildingExpenseRuleService $rules
    ) {
    }

    public function execute(
        BuildingExpense $model,
        array $data,
        User $actor
    ): BuildingExpense
    {
        return DB::transaction(function () use ($model, $data, $actor): BuildingExpense {
            Building::query()->whereKey($model->building_id)->lockForUpdate()->firstOrFail();

            $schedules = app(BuildingBillScheduleService::class);
            $model = BuildingExpense::whereKey($model->id)->lockForUpdate()->firstOrFail();
            $data = $schedules->prepare($data, $model);

            $status = $data['status'] ?? $model->status;

            if (
                in_array($status, ['approved', 'posted'], true)
                && ! $model->approved_at
            ) {
                $data['approved_by'] = $actor->getKey();
                $data['approved_at'] = now();
            }

            if ($status === 'posted' && ! $model->posted_at) {
                $data['posted_at'] = now();
            }

            $model->update($data);

            $this->rules->synchronize($model->refresh(), $model->financial_category_id !== null || isset($data['payment_status']));

            $schedules->replace($model);

            return $model->refresh();
        });
    }
}
