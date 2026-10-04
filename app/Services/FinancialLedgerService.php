<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Building;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
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

        return DB::transaction(function () use (
            $building,
            $actor,
            $data,
            $entries
        ): FinancialTransaction {
            $accounts = $this->validateAndLockEntries(
                $building,
                $entries
            );

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
                $account = $accounts->get(
                    (int) $entry['financial_account_id']
                );

                $transaction->financialLedgerEntries()->create([
                    'financial_account_id' => $account->getKey(),
                    'entry_type' => $this->entryType($entry)->value,
                    'amount' => (int) $entry['amount'],
                    'currency' => $this->currencyFor(
                        $entry,
                        $building
                    ),
                    'metadata' => $entry['metadata'] ?? null,
                ]);
            }

            return $transaction->refresh();
        }, 3);
    }

    /**
     * Validate ledger invariants and lock all referenced accounts in a
     * deterministic order. Validation and persistence must share the same
     * transaction to avoid a TOCTOU race with account state changes.
     *
     * @return Collection<int, FinancialAccount>
     */
    private function validateAndLockEntries(
        Building $building,
        array $entries
    ): Collection {
        if (count($entries) < 2) {
            throw ValidationException::withMessages([
                'entries' => 'At least two ledger entries are required.',
            ]);
        }

        $accountIds = collect($entries)
            ->pluck('financial_account_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $accounts = FinancialAccount::query()
            ->where('building_id', $building->getKey())
            ->where('is_active', true)
            ->whereIn('id', $accountIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(
                fn (FinancialAccount $account): int =>
                    (int) $account->getKey()
            );

        if ($accounts->count() !== $accountIds->count()) {
            throw ValidationException::withMessages([
                'entries' => 'All ledger accounts must be active accounts of the selected building.',
            ]);
        }

        $totals = [];

        foreach ($entries as $entry) {
            $amount = (int) ($entry['amount'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'entries' => 'Ledger entry amounts must be greater than zero.',
                ]);
            }

            $currency = $this->currencyFor(
                $entry,
                $building
            );
            $type = $this->entryType($entry)->value;

            $totals[$currency] ??= [
                LedgerEntryType::Debit->value => 0,
                LedgerEntryType::Credit->value => 0,
            ];

            $totals[$currency][$type] += $amount;
        }

        foreach ($totals as $currency => $total) {
            if (
                $total[LedgerEntryType::Debit->value] <= 0
                || $total[LedgerEntryType::Credit->value] <= 0
                || $total[LedgerEntryType::Debit->value]
                    !== $total[LedgerEntryType::Credit->value]
            ) {
                throw ValidationException::withMessages([
                    'entries' => sprintf(
                        'Ledger entries are not balanced for currency %s.',
                        $currency
                    ),
                ]);
            }
        }

        return $accounts;
    }

    private function entryType(array $entry): LedgerEntryType
    {
        $type = $entry['entry_type'] ?? null;

        if ($type instanceof LedgerEntryType) {
            return $type;
        }

        $resolved = is_string($type)
            ? LedgerEntryType::tryFrom($type)
            : null;

        if (! $resolved) {
            throw ValidationException::withMessages([
                'entries' => 'Ledger entry type must be debit or credit.',
            ]);
        }

        return $resolved;
    }

    private function currencyFor(
        array $entry,
        Building $building
    ): string {
        $currency = strtoupper(
            (string) (
                $entry['currency']
                ?? $building->currency
                ?? 'IRR'
            )
        );

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages([
                'entries' => 'Ledger entry currency must be a three-letter ISO-style code.',
            ]);
        }

        return $currency;
    }
}
