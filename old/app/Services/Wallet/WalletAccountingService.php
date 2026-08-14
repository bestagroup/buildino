<?php

namespace App\Services\Wallet;

use App\Enums\FinancialTransactionType;
use App\Enums\LedgerEntryType;
use App\Enums\WalletAccountingPostingStatus;
use App\Enums\WalletTransferStatus;
use App\Enums\WalletTransferType;
use App\Models\Building;
use App\Models\BuildingBillPayment;
use App\Models\ReservationCancellation;
use App\Models\User;
use App\Models\WalletAccountingPosting;
use App\Models\WalletTransfer;
use App\Services\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletAccountingService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly BuildingWalletAccountingProfileService $profiles
    ) {
    }

    public function process(
        WalletTransfer|int $transfer,
        ?User $actor = null
    ): WalletAccountingPosting {
        $transferId = $transfer instanceof WalletTransfer
            ? $transfer->getKey()
            : $transfer;

        try {
            return DB::transaction(function () use (
                $transferId,
                $actor
            ): WalletAccountingPosting {
                $transfer = WalletTransfer::query()
                    ->with([
                        'sourceWallet.owner',
                        'destinationWallet.owner',
                        'reference',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($transferId);

                if (
                    $transfer->status
                    !== WalletTransferStatus::Completed
                ) {
                    throw ValidationException::withMessages([
                        'wallet_transfer' =>
                            'Only completed Wallet transfers may be posted to accounting.',
                    ]);
                }

                $posting = WalletAccountingPosting::query()
                    ->where(
                        'wallet_transfer_id',
                        $transfer->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $posting
                    && in_array(
                        $posting->status,
                        [
                            WalletAccountingPostingStatus::Posted,
                            WalletAccountingPostingStatus::Skipped,
                        ],
                        true
                    )
                ) {
                    return $posting;
                }

                if (! $posting) {
                    $posting = WalletAccountingPosting::query()
                        ->create([
                            'uuid' => (string) Str::uuid(),
                            'wallet_transfer_id' =>
                                $transfer->getKey(),
                            'status' =>
                                WalletAccountingPostingStatus::Pending,
                            'attempts' => 0,
                        ]);
                }

                $posting->increment('attempts');

                $mapping = $this->mappingFor(
                    $transfer
                );

                if ($mapping['action'] === 'skip') {
                    $posting->update([
                        'building_id' =>
                            $mapping['building']?->getKey(),
                        'financial_transaction_id' => null,
                        'status' =>
                            WalletAccountingPostingStatus::Skipped,
                        'mapping_key' =>
                            $mapping['mapping_key'],
                        'reason' =>
                            $mapping['reason'],
                        'mapping_snapshot' =>
                            $mapping['snapshot'],
                        'last_error' => null,
                        'posted_at' => null,
                    ]);

                    return $posting->refresh();
                }

                /** @var Building $building */
                $building = $mapping['building'];

                $profile = $this->profiles->forBuilding(
                    $building
                );

                if (! $profile->is_active) {
                    throw ValidationException::withMessages([
                        'accounting_profile' =>
                            'Wallet accounting profile is inactive for this building.',
                    ]);
                }

                $profile->loadMissing([
                    'walletAssetAccount',
                    'chargeCollectionCreditAccount',
                    'facilityIncomeAccount',
                    'billExpenseAccount',
                    'bankClearingAccount',
                ]);

                $entries = $this->entriesFor(
                    $transfer,
                    $profile,
                    $mapping['mapping_key']
                );

                $financialTransaction =
                    $this->ledger->post(
                        $building,
                        $actor
                            ?? $transfer->createdBy
                            ?? null,
                        [
                            'transaction_type' =>
                                $mapping['transaction_type'],
                            'occurred_at' =>
                                $transfer->completed_at
                                    ?? now(),
                            'reference_type' =>
                                $transfer->getMorphClass(),
                            'reference_id' =>
                                $transfer->getKey(),
                            'description' =>
                                $mapping['description'],
                            'entries' => $entries,
                        ]
                    );

                $posting->update([
                    'building_id' =>
                        $building->getKey(),
                    'financial_transaction_id' =>
                        $financialTransaction->getKey(),
                    'status' =>
                        WalletAccountingPostingStatus::Posted,
                    'mapping_key' =>
                        $mapping['mapping_key'],
                    'reason' => null,
                    'mapping_snapshot' => [
                        ...$mapping['snapshot'],
                        'profile_id' =>
                            $profile->getKey(),
                        'entry_account_ids' =>
                            collect($entries)
                                ->pluck(
                                    'financial_account_id'
                                )
                                ->values()
                                ->all(),
                    ],
                    'last_error' => null,
                    'posted_at' => now(),
                ]);

                return $posting->refresh();
            }, 3);
        } catch (\Throwable $exception) {
            $this->recordFailure(
                $transferId,
                $exception
            );

            throw $exception;
        }
    }

    public function recordFailure(
        int $transferId,
        \Throwable $exception
    ): void {
        try {
            DB::transaction(function () use (
                $transferId,
                $exception
            ): void {
                $transfer = WalletTransfer::query()
                    ->find($transferId);

                if (! $transfer) {
                    return;
                }

                $posting = WalletAccountingPosting::query()
                    ->firstOrCreate(
                        [
                            'wallet_transfer_id' =>
                                $transferId,
                        ],
                        [
                            'uuid' =>
                                (string) Str::uuid(),
                            'status' =>
                                WalletAccountingPostingStatus::Failed,
                            'attempts' => 0,
                        ]
                    );

                if (
                    $posting->status
                    === WalletAccountingPostingStatus::Posted
                ) {
                    return;
                }

                $posting->update([
                    'status' =>
                        WalletAccountingPostingStatus::Failed,
                    'attempts' =>
                        max(
                            1,
                            (int) $posting->attempts + 1
                        ),
                    'last_error' =>
                        mb_substr(
                            $exception->getMessage(),
                            0,
                            5000
                        ),
                ]);
            }, 3);
        } catch (\Throwable) {
            /*
             * Accounting failure recording must never mask or alter
             * an already-committed Wallet transfer.
             */
        }
    }

    private function mappingFor(
        WalletTransfer $transfer
    ): array {
        $sourceOwner =
            $transfer->sourceWallet?->owner;

        $destinationOwner =
            $transfer->destinationWallet?->owner;

        $snapshot = [
            'wallet_transfer_type' =>
                $transfer->type->value,
            'source_wallet_id' =>
                $transfer->source_wallet_id,
            'destination_wallet_id' =>
                $transfer->destination_wallet_id,
            'source_owner_type' =>
                $transfer->sourceWallet?->owner_type,
            'source_owner_id' =>
                $transfer->sourceWallet?->owner_id,
            'destination_owner_type' =>
                $transfer->destinationWallet?->owner_type,
            'destination_owner_id' =>
                $transfer->destinationWallet?->owner_id,
            'reference_type' =>
                $transfer->reference_type,
            'reference_id' =>
                $transfer->reference_id,
            'amount' => (int) $transfer->amount,
            'currency' => $transfer->currency,
        ];

        if (
            $transfer->type
            === WalletTransferType::ChargeCollection
            && $destinationOwner instanceof Building
        ) {
            return [
                'action' => 'post',
                'building' => $destinationOwner,
                'mapping_key' =>
                    'building_charge_collection',
                'transaction_type' =>
                    FinancialTransactionType::Income,
                'description' =>
                    'Wallet building charge collection',
                'snapshot' => $snapshot,
            ];
        }

        if (
            $transfer->type
            === WalletTransferType::FacilityFee
            && $destinationOwner instanceof Building
        ) {
            return [
                'action' => 'post',
                'building' => $destinationOwner,
                'mapping_key' =>
                    'building_facility_income',
                'transaction_type' =>
                    FinancialTransactionType::Income,
                'description' =>
                    'Wallet facility fee income',
                'snapshot' => $snapshot,
            ];
        }

        if (
            $transfer->type
            === WalletTransferType::Refund
            && $sourceOwner instanceof Building
            && $transfer->reference
                instanceof ReservationCancellation
        ) {
            return [
                'action' => 'post',
                'building' => $sourceOwner,
                'mapping_key' =>
                    'building_facility_refund',
                'transaction_type' =>
                    FinancialTransactionType::Refund,
                'description' =>
                    'Wallet facility reservation refund',
                'snapshot' => $snapshot,
            ];
        }

        if (
            $transfer->type
            === WalletTransferType::BillPayment
            && $sourceOwner instanceof Building
            && (
                $transfer->reference === null
                || $transfer->reference
                    instanceof BuildingBillPayment
            )
        ) {
            return [
                'action' => 'post',
                'building' => $sourceOwner,
                'mapping_key' =>
                    'building_bill_payment',
                'transaction_type' =>
                    FinancialTransactionType::Expense,
                'description' =>
                    'Wallet building bill payment',
                'snapshot' => $snapshot,
            ];
        }

        if (
            $transfer->type
            === WalletTransferType::Payout
            && $sourceOwner instanceof Building
        ) {
            return [
                'action' => 'post',
                'building' => $sourceOwner,
                'mapping_key' =>
                    'building_wallet_to_bank',
                'transaction_type' =>
                    FinancialTransactionType::Adjustment,
                'description' =>
                    'Building Wallet to bank account transfer',
                'snapshot' => $snapshot,
            ];
        }

        return [
            'action' => 'skip',
            'building' =>
                $sourceOwner instanceof Building
                    ? $sourceOwner
                    : (
                        $destinationOwner
                            instanceof Building
                            ? $destinationOwner
                            : null
                    ),
            'mapping_key' =>
                'no_building_ledger_mapping',
            'reason' =>
                $this->skipReason($transfer),
            'snapshot' => $snapshot,
        ];
    }

    private function entriesFor(
        WalletTransfer $transfer,
        $profile,
        string $mappingKey
    ): array {
        $amount = (int) $transfer->amount;
        $currency = strtoupper(
            $transfer->currency
        );

        return match ($mappingKey) {
            'building_charge_collection' => [
                $this->entry(
                    $profile->wallet_asset_account_id,
                    LedgerEntryType::Debit,
                    $amount,
                    $currency,
                    'building_wallet_asset'
                ),
                $this->entry(
                    $profile->charge_collection_credit_account_id,
                    LedgerEntryType::Credit,
                    $amount,
                    $currency,
                    'charge_collection_credit'
                ),
            ],

            'building_facility_income' => [
                $this->entry(
                    $profile->wallet_asset_account_id,
                    LedgerEntryType::Debit,
                    $amount,
                    $currency,
                    'building_wallet_asset'
                ),
                $this->entry(
                    $profile->facility_income_account_id,
                    LedgerEntryType::Credit,
                    $amount,
                    $currency,
                    'facility_income'
                ),
            ],

            'building_facility_refund' => [
                $this->entry(
                    $profile->facility_income_account_id,
                    LedgerEntryType::Debit,
                    $amount,
                    $currency,
                    'facility_income_reversal'
                ),
                $this->entry(
                    $profile->wallet_asset_account_id,
                    LedgerEntryType::Credit,
                    $amount,
                    $currency,
                    'building_wallet_asset'
                ),
            ],

            'building_bill_payment' => [
                $this->entry(
                    $profile->bill_expense_account_id,
                    LedgerEntryType::Debit,
                    $amount,
                    $currency,
                    'building_bill_expense'
                ),
                $this->entry(
                    $profile->wallet_asset_account_id,
                    LedgerEntryType::Credit,
                    $amount,
                    $currency,
                    'building_wallet_asset'
                ),
            ],

            'building_wallet_to_bank' => [
                $this->entry(
                    $profile->bank_clearing_account_id,
                    LedgerEntryType::Debit,
                    $amount,
                    $currency,
                    'building_bank_clearing'
                ),
                $this->entry(
                    $profile->wallet_asset_account_id,
                    LedgerEntryType::Credit,
                    $amount,
                    $currency,
                    'building_wallet_asset'
                ),
            ],

            default => throw ValidationException::withMessages([
                'mapping' =>
                    'Unsupported Wallet accounting mapping.',
            ]),
        };
    }

    private function entry(
        int $accountId,
        LedgerEntryType $type,
        int $amount,
        string $currency,
        string $role
    ): array {
        return [
            'financial_account_id' =>
                $accountId,
            'entry_type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'wallet_accounting_role' =>
                    $role,
            ],
        ];
    }

    private function skipReason(
        WalletTransfer $transfer
    ): string {
        return match ($transfer->type) {
            WalletTransferType::TopUp =>
                'External top-up belongs to a personal/unit money flow and has no automatic Building ledger mapping.',

            WalletTransferType::InternalTransfer =>
                'Generic internal transfer requires an explicit accounting context.',

            WalletTransferType::ServiceProviderPayment =>
                'Provider settlement belongs to the provider personal Wallet, not the Building ledger.',

            WalletTransferType::PlatformCommission =>
                'Platform commission belongs to the Platform accounting entity, not the Building ledger.',

            WalletTransferType::ProviderPayout =>
                'Provider payout belongs to the provider personal Wallet, not the Building ledger.',

            WalletTransferType::Adjustment =>
                'Wallet adjustments require explicit manual accounting classification.',

            WalletTransferType::Refund =>
                'Refund reference is not a supported Building facility cancellation.',

            default =>
                'Wallet transfer has no automatic Building ledger mapping.',
        };
    }
}
