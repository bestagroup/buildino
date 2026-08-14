<?php

namespace App\Services\Wallet;

use App\Enums\InvoiceStatus;
use App\Enums\UnitChargePayerSource;
use App\Models\BuildingChargePolicy;
use App\Models\Unit;
use App\Models\UnitChargeSetting;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Models\WalletTopUp;
use App\Services\Charge\WalletChargePeriodService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class WalletOutstandingRetryService
{
    public function __construct(
        private readonly WalletChargePeriodService $charges
    ) {
    }

    public function retry(
        WalletTopUp $topUp,
        User $actor
    ): array {
        $topUp->loadMissing([
            'target',
            'wallet',
        ]);

        $summary = [
            'target' => $topUp->targetKind(),
            'candidate_invoices' => 0,
            'processed_invoices' => 0,
            'paid_invoices' => 0,
            'partial_invoices' => 0,
            'collected_amount' => 0,
            'skipped_invoices' => 0,
            'invoice_ids' => [],
        ];

        if ($topUp->target instanceof Unit) {
            return $this->retryUnit(
                $topUp->target,
                $actor,
                $summary
            );
        }

        if ($topUp->target instanceof User) {
            return $this->retryUser(
                $topUp->target,
                $actor,
                $summary
            );
        }

        $summary['reason'] = 'unsupported_topup_target';

        return $summary;
    }

    private function retryUnit(
        Unit $unit,
        User $actor,
        array $summary
    ): array {
        $unit->loadMissing('floor.block.building');

        $building = $unit->floor?->block?->building;

        if (! $building) {
            $summary['reason'] = 'unit_has_no_building';

            return $summary;
        }

        $policy = BuildingChargePolicy::query()
            ->where('building_id', $building->getKey())
            ->where('is_active', true)
            ->first();

        if (! $policy || ! $policy->auto_collect) {
            $summary['reason'] = 'building_auto_collect_disabled';

            return $summary;
        }

        $setting = UnitChargeSetting::query()
            ->where('unit_id', $unit->getKey())
            ->first();

        /*
         * Without a UnitChargeSetting, the established default source
         * is Unit Wallet and auto collection is enabled by the building
         * policy. An explicit setting can disable or redirect collection.
         */
        if ($setting) {
            if (! $setting->auto_collect) {
                $summary['reason'] = 'unit_auto_collect_disabled';

                return $summary;
            }

            if (
                $setting->payer_source
                !== UnitChargePayerSource::UnitWallet
            ) {
                $summary['reason'] = 'unit_wallet_is_not_charge_source';

                return $summary;
            }
        }

        $allowPartial = $setting?->allow_partial
            ?? (bool) $policy->allow_partial;

        return $this->collectInvoices(
            $this->payableChargeInvoices(
                collect([$unit->getKey()])
            ),
            $actor,
            $allowPartial,
            $summary
        );
    }

    private function retryUser(
        User $user,
        User $actor,
        array $summary
    ): array {
        $settings = UnitChargeSetting::query()
            ->with('unit.floor.block.building')
            ->where(
                'payer_source',
                UnitChargePayerSource::UserWallet->value
            )
            ->where(
                'payer_user_id',
                $user->getKey()
            )
            ->where('auto_collect', true)
            ->orderBy('unit_id')
            ->get();

        if ($settings->isEmpty()) {
            $summary['reason'] = 'no_user_wallet_charge_sources';

            return $summary;
        }

        foreach ($settings as $setting) {
            $unit = $setting->unit;
            $building = $unit?->floor?->block?->building;

            if (! $unit || ! $building) {
                continue;
            }

            $policy = BuildingChargePolicy::query()
                ->where('building_id', $building->getKey())
                ->where('is_active', true)
                ->first();

            if (! $policy || ! $policy->auto_collect) {
                continue;
            }

            $summary = $this->collectInvoices(
                $this->payableChargeInvoices(
                    collect([$unit->getKey()])
                ),
                $actor,
                (bool) $setting->allow_partial,
                $summary
            );
        }

        return $summary;
    }

    private function payableChargeInvoices(
        Collection $unitIds
    ): Collection {
        if ($unitIds->isEmpty()) {
            return collect();
        }

        return UnitInvoice::query()
            ->whereIn('unit_id', $unitIds->all())
            ->whereNotNull('charge_period_id')
            ->where('outstanding_amount', '>', 0)
            ->whereIn(
                'status',
                [
                    InvoiceStatus::Issued->value,
                    InvoiceStatus::Partial->value,
                    InvoiceStatus::Overdue->value,
                ]
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    private function collectInvoices(
        Collection $invoices,
        User $actor,
        bool $allowPartial,
        array $summary
    ): array {
        $summary['candidate_invoices'] += $invoices->count();

        foreach ($invoices as $invoice) {
            $beforePaid = (int) $invoice->paid_amount;

            try {
                $updated = $this->charges->collectInvoice(
                    $invoice,
                    $actor,
                    $allowPartial
                );
            } catch (ValidationException) {
                $summary['skipped_invoices']++;

                continue;
            }

            $collected = max(
                0,
                (int) $updated->paid_amount - $beforePaid
            );

            if ($collected <= 0) {
                $summary['skipped_invoices']++;

                continue;
            }

            $summary['processed_invoices']++;
            $summary['collected_amount'] += $collected;
            $summary['invoice_ids'][] = $updated->getKey();

            if ($updated->status === InvoiceStatus::Paid) {
                $summary['paid_invoices']++;
            } else {
                $summary['partial_invoices']++;
            }
        }

        return $summary;
    }
}
