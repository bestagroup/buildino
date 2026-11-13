<?php

namespace App\Services;

use App\Enums\ChargePeriodStatus;
use App\Models\Building;
use App\Models\ChargePeriod;
use App\Models\InvoicePeriodAllocation;
use App\Models\UnitInvoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class InvoicePeriodScheduleService
{
    /** Call inside the transaction, before locking units or invoices. */
    public function lockBuilding(int $buildingId): void
    {
        Building::whereKey($buildingId)->lockForUpdate()->firstOrFail();
    }

    public function periods(int $buildingId, int $startId, int $count): Collection
    {
        if ($count < 1 || $count > 5) {
            throw ValidationException::withMessages(['period_installment_count' => 'تعداد اقساط باید بین ۱ تا ۵ باشد.']);
        }
        $start = ChargePeriod::where('building_id', $buildingId)->find($startId);
        if (! $start) {
            throw ValidationException::withMessages(['start_charge_period_id' => 'دوره متعلق به همین ساختمان را انتخاب کنید.']);
        }
        $periods = ChargePeriod::where('building_id', $buildingId)
            ->where('status', '!=', ChargePeriodStatus::Cancelled->value)
            ->whereDate('period_start', '>=', $start->period_start->toDateString())
            ->orderBy('period_start')->orderBy('id')->limit($count)->lockForUpdate()->get();
        if ($periods->count() !== $count || $periods->first()?->id !== $startId
            || $periods->contains(fn ($period) => ! in_array($period->status, [ChargePeriodStatus::Draft, ChargePeriodStatus::Calculated], true))) {
            throw ValidationException::withMessages(['start_charge_period_id' => 'از دوره انتخاب‌شده به تعداد اقساط، دوره متوالی باز و قابل محاسبه لازم است؛ ابتدا دوره‌های موردنیاز را تعریف کنید.']);
        }

        return $periods;
    }

    public function assertEditable(UnitInvoice $invoice): void
    {
        if ($invoice->periodAllocations()->whereNotNull('issued_invoice_id')->exists()
            || $invoice->periodAllocations()->whereHas('chargePeriod', fn ($query) => $query->whereNotIn('status', ['draft', 'calculated']))->exists()) {
            throw ValidationException::withMessages(['invoice' => 'پس از صدور یا بسته‌شدن یکی از دوره‌های اقساط، تغییر یا حذف برنامه بدهی مجاز نیست.']);
        }
    }

    public function replace(UnitInvoice $invoice, Collection $periods): void
    {
        $this->assertEditable($invoice);
        $total = (int) $invoice->total_amount;
        $count = $periods->count();
        if (abs($total) < $count) {
            throw ValidationException::withMessages(['items' => 'مبلغ بدهی یا طلب باید غیرصفر و برای تقسیم به تعداد اقساط کافی باشد.']);
        }
        $base = intdiv($total, $count);
        $affectedPeriods = $invoice->periodAllocations()->pluck('charge_period_id')->merge($periods->modelKeys());
        ChargePeriod::whereIn('id', $affectedPeriods)->where('status', ChargePeriodStatus::Calculated->value)
            ->update(['status' => ChargePeriodStatus::Draft->value, 'calculation_source_hash' => null]);
        $invoice->periodAllocations()->delete();
        foreach ($periods->values() as $index => $period) {
            $invoice->periodAllocations()->create([
                'charge_period_id' => $period->id, 'sequence' => $index + 1,
                'amount' => $base + ($index === $count - 1 ? $total - $base * $count : 0),
            ]);
        }
    }

    public function forPeriod(ChargePeriod $period): Collection
    {
        return InvoicePeriodAllocation::where('charge_period_id', $period->id)
            ->whereHas('sourceInvoice', fn ($query) => $query->where('building_id', $period->building_id)->whereNotNull('start_charge_period_id'))
            ->with('sourceInvoice.invoiceItems')->orderBy('id')->get();
    }
}
