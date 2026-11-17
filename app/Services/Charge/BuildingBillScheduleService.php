<?php

namespace App\Services\Charge;

use App\Models\BuildingExpense;
use App\Models\ChargeExpenseAllocation;
use App\Models\ChargePeriod;
use App\Models\Unit;
use App\Services\InvoicePeriodScheduleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Uses the same building mutex as charge calculation and issuance. */
final class BuildingBillScheduleService
{
    public function prepare(array $data, ?BuildingExpense $expense = null): array
    {
        $merged = array_replace($expense?->getAttributes() ?? [], $data);
        if ($expense) {
            $this->assertEditable($expense);
            $merged = array_replace($expense->toArray(), $data);
        }
        if (empty($merged['start_charge_period_id'])) {
            if (($merged['amount'] ?? 1) < 1 || ($merged['distribution_mode'] ?? 'formula') !== 'formula' || ! empty($merged['target_unit_ids']) || ! empty($merged['items'])) {
                throw ValidationException::withMessages(['start_charge_period_id' => 'برای سند جدید دوره پرداخت را انتخاب کنید.']);
            }

            return $data; // Existing unscheduled expenses retain their original date-based behaviour.
        }
        Validator::make($merged, [
            'start_charge_period_id' => 'required|integer',
            'period_installment_count' => 'required|integer|between:1,5',
            'distribution_mode' => 'required|in:formula,equal',
            'target_unit_ids' => 'nullable|array|min:1|max:10000',
            'target_unit_ids.*' => 'required|integer|distinct',
            'items' => 'required|array|min:1|max:100',
            'items.*.title' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|between:1,100000',
            'items.*.unit_amount' => 'required|integer|between:-1000000000000,1000000000000',
            'items.*.description' => 'nullable|string|max:2000',
        ])->validate();
        $total = 0;
        $items = [];
        foreach ($merged['items'] as $item) {
            $total += (int) $item['quantity'] * (int) $item['unit_amount'];
            $items[] = array_intersect_key($item, array_flip(['title', 'quantity', 'unit_amount', 'description']));
        }
        if (abs($total) < (int) $merged['period_installment_count'] || abs($total) > 9000000000000000) {
            throw ValidationException::withMessages(['items' => 'جمع مبلغ باید غیرصفر، در محدوده مجاز و برای تعداد اقساط کافی باشد.']);
        }
        $units = Unit::where('is_active', true)->whereHas('floor.block', fn ($query) => $query
            ->where('building_id', $merged['building_id'])
            ->when($merged['block_id'] ?? null, fn ($query, $block) => $query->whereKey($block)))->pluck('id');
        $targets = $merged['target_unit_ids'] ?? null;
        if ($units->isEmpty() || ($targets !== null && collect($targets)->diff($units)->isNotEmpty())) {
            throw ValidationException::withMessages(['target_unit_ids' => 'فقط واحدهای فعال ساختمان و بلوک انتخاب‌شده قابل تخصیص هستند.']);
        }
        app(InvoicePeriodScheduleService::class)->periods((int) $merged['building_id'], (int) $merged['start_charge_period_id'], (int) $merged['period_installment_count']);

        return [...$data, 'status' => $data['status'] ?? ($expense?->start_charge_period_id ? $expense->status : 'posted'), 'amount' => $total, 'items' => $items, 'target_unit_ids' => $targets === null ? null : array_map('intval', $targets)];
    }

    public function assertEditable(BuildingExpense $expense): void
    {
        $closedSchedule = $expense->periodAllocations()->whereHas('chargePeriod', fn ($q) => $q->whereNotIn('status', ['draft', 'calculated']))->exists();
        $issued = ChargeExpenseAllocation::where('building_expense_id', $expense->id)
            ->whereHas('chargePeriod', fn ($q) => $q->whereNotIn('status', ['draft', 'calculated']))->exists();
        if ($closedSchedule || $issued) {
            throw ValidationException::withMessages(['expense' => 'بعد از صدور یا بسته‌شدن یکی از دوره‌ها، تغییر یا حذف این سند مجاز نیست.']);
        }
    }

    public function replace(BuildingExpense $expense): void
    {
        if (! $expense->start_charge_period_id) {
            return;
        }
        $periods = app(InvoicePeriodScheduleService::class)->periods($expense->building_id, $expense->start_charge_period_id, $expense->period_installment_count);
        $this->invalidate($expense->periodAllocations()->pluck('charge_period_id')->merge($periods->modelKeys()));
        $expense->periodAllocations()->delete();
        $base = intdiv($expense->amount, $periods->count());
        foreach ($periods->values() as $index => $period) {
            $expense->periodAllocations()->create([
                'charge_period_id' => $period->id, 'sequence' => $index + 1,
                'amount' => $base + ($index === $periods->count() - 1 ? $expense->amount - $base * $periods->count() : 0),
            ]);
        }
    }

    public function invalidate(Collection $ids): void
    {
        ChargePeriod::whereIn('id', $ids)->where('status', 'calculated')->update(['status' => 'draft', 'calculation_source_hash' => null]);
    }
}
