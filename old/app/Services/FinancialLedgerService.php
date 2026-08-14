<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Building;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FinancialLedgerService
{
    public function post(
        Building $building,
        ?User $actor,
        array $data
    ): FinancialTransaction {
        $entries = $data['entries'];

        $this->validateEntries($building, $entries);

        return DB::transaction(function () use (
            $building,
            $actor,
            $data,
            $entries
        ): FinancialTransaction {
            $transaction = FinancialTransaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_id' => $building->getKey(),
                'transaction_type' => $data['transaction_type'],
                'occurred_at' => $data['occurred_at'] ?? now(),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $actor?->getKey(),
            ]);

            foreach ($entries as $entry) {
                $transaction->financialLedgerEntries()->create([
                    'financial_account_id' => $entry['financial_account_id'],
                    'entry_type' => $entry['entry_type'],
                    'amount' => $entry['amount'],
                    'currency' => strtoupper(
                        $entry['currency']
                        ?? $building->currency
                        ?? 'IRR'
                    ),
                    'metadata' => $entry['metadata'] ?? null,
                ]);
            }

            return $transaction->refresh();
        });
    }

    private function validateEntries(
        Building $building,
        array $entries
    ): void {
        if (count($entries) < 2) {
            throw ValidationException::withMessages([
                'entries' => 'At least two ledger entries are required.',
            ]);
        }

        $accountIds = collect($entries)
            ->pluck('financial_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validAccounts = FinancialAccount::query()
            ->where('building_id', $building->getKey())
            ->where('is_active', true)
            ->whereIn('id', $accountIds)
            ->count();

        if ($validAccounts !== $accountIds->count()) {
            throw ValidationException::withMessages([
                'entries' => 'All ledger accounts must be active accounts of the selected building.',
            ]);
        }

        $totals = [];

        foreach ($entries as $entry) {
            $currency = strtoupper(
                $entry['currency']
                ?? $building->currency
                ?? 'IRR'
            );

            $type = $entry['entry_type'] instanceof LedgerEntryType
                ? $entry['entry_type']->value
                : $entry['entry_type'];

            $totals[$currency] ??= [
                'debit' => 0,
                'credit' => 0,
            ];

            $totals[$currency][$type] += (int) $entry['amount'];
        }

        foreach ($totals as $currency => $total) {
            if (
                $total['debit'] <= 0
                || $total['credit'] <= 0
                || $total['debit'] !== $total['credit']
            ) {
                throw ValidationException::withMessages([
                    'entries' => sprintf(
                        'Ledger entries are not balanced for currency %s.',
                        $currency
                    ),
                ]);
            }
        }
    }
}
