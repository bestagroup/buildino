<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('loyalty_accounts')
                ->orderBy('id')
                ->each(function (object $account): void {
                    $transactions = DB::table('loyalty_transactions')
                        ->where('loyalty_account_id', $account->id)
                        ->orderBy('id')
                        ->get();
                    $running = 0;
                    $sources = [];

                    foreach ($transactions as $transaction) {
                        $points = (int) $transaction->points;

                        if (in_array($transaction->type, ['spend', 'expire'], true)) {
                            $points = -abs($points);
                        } elseif ($transaction->type === 'earn') {
                            $points = abs($points);
                        }

                        $running += $points;
                        DB::table('loyalty_transactions')
                            ->where('id', $transaction->id)
                            ->update([
                                'points' => $points,
                                'balance_after' => $running,
                                'remaining_points' => $points > 0 ? $points : null,
                            ]);

                        if ($points > 0) {
                            $sources[(int) $transaction->id] = $points;
                        } elseif ($points < 0) {
                            $this->allocate(
                                (int) $transaction->id,
                                abs($points),
                                $sources
                            );
                        }
                    }

                    $difference = (int) $account->balance - $running;

                    if ($difference !== 0) {
                        $id = DB::table('loyalty_transactions')->insertGetId([
                            'loyalty_account_id' => $account->id,
                            'type' => 'adjust',
                            'points' => $difference,
                            'balance_after' => (int) $account->balance,
                            'remaining_points' => $difference > 0
                                ? $difference
                                : null,
                            'idempotency_key' => "migration:legacy-loyalty-balance:{$account->id}",
                            'description' => 'Legacy loyalty balance reconciliation',
                            'metadata' => json_encode([
                                'migration' => '2026_08_20_240000',
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        if ($difference > 0) {
                            $sources[$id] = $difference;
                        } else {
                            $this->allocate($id, abs($difference), $sources);
                        }
                    }

                    foreach ($sources as $sourceId => $remaining) {
                        DB::table('loyalty_transactions')
                            ->where('id', $sourceId)
                            ->update(['remaining_points' => $remaining]);
                    }
                });
        }, 3);
    }

    public function down(): void
    {
        // Historical ledger reconciliation is intentionally irreversible.
    }

    /** @param array<int, int> $sources */
    private function allocate(
        int $spendTransactionId,
        int $points,
        array &$sources
    ): void {
        $remaining = $points;

        foreach ($sources as $sourceId => $available) {
            if ($remaining === 0) {
                break;
            }

            $allocated = min($remaining, $available);

            if ($allocated === 0) {
                continue;
            }

            DB::table('loyalty_transaction_allocations')->insertOrIgnore([
                'spend_transaction_id' => $spendTransactionId,
                'earn_transaction_id' => $sourceId,
                'points' => $allocated,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sources[$sourceId] -= $allocated;
            $remaining -= $allocated;
        }
    }
};
