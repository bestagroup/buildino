<?php

namespace App\Services\Loyalty;

use App\Enums\LoyaltyClaimStatus;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyRewardClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyRewardService
{
    public function __construct(
        private readonly LoyaltyLedgerService $ledger,
        private readonly LoyaltyAccessService $access
    ) {}

    public function claim(
        LoyaltyReward $reward,
        User $user,
        string $idempotencyKey
    ): LoyaltyRewardClaim {
        return DB::transaction(function () use (
            $idempotencyKey,
            $reward,
            $user
        ): LoyaltyRewardClaim {
            $existing = LoyaltyRewardClaim::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->user_id !== (int) $user->getKey()
                    || (int) $existing->loyalty_reward_id !== (int) $reward->getKey()
                ) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'The idempotency key belongs to another reward claim.',
                    ]);
                }

                return $existing;
            }

            $reward = LoyaltyReward::query()
                ->lockForUpdate()
                ->findOrFail($reward->getKey());

            if (! $reward->is_active || ! $this->access->canClaim($user, $reward)) {
                throw ValidationException::withMessages([
                    'reward' => 'This reward is not available in the current resident scope.',
                ]);
            }

            $claim = LoyaltyRewardClaim::query()->create([
                'loyalty_reward_id' => $reward->getKey(),
                'user_id' => $user->getKey(),
                'claimed_at' => now(),
                'status' => LoyaltyClaimStatus::Pending,
                'idempotency_key' => $idempotencyKey,
            ]);

            $spend = $this->ledger->spend(
                $user,
                (int) $reward->required_points,
                "loyalty:claim:{$claim->id}",
                $claim,
                "Claimed reward {$reward->title}",
                [
                    'reward_id' => $reward->getKey(),
                    'required_points' => (int) $reward->required_points,
                ]
            );

            $claim->update([
                'loyalty_transaction_id' => $spend->getKey(),
            ]);

            return $claim->refresh();
        }, 3);
    }

    public function approve(
        LoyaltyRewardClaim $claim,
        User $actor
    ): LoyaltyRewardClaim {
        return DB::transaction(function () use ($actor, $claim): LoyaltyRewardClaim {
            $claim = LoyaltyRewardClaim::query()
                ->lockForUpdate()
                ->findOrFail($claim->getKey());

            if ($claim->status === LoyaltyClaimStatus::Approved) {
                return $claim;
            }

            if ($claim->status !== LoyaltyClaimStatus::Pending) {
                throw ValidationException::withMessages([
                    'status' => 'Only a pending reward claim can be approved.',
                ]);
            }

            $claim->update([
                'status' => LoyaltyClaimStatus::Approved,
                'processed_by' => $actor->getKey(),
                'processed_at' => now(),
                'rejection_reason' => null,
            ]);

            return $claim->refresh();
        }, 3);
    }

    public function reject(
        LoyaltyRewardClaim $claim,
        User $actor,
        string $reason
    ): LoyaltyRewardClaim {
        return DB::transaction(function () use (
            $actor,
            $claim,
            $reason
        ): LoyaltyRewardClaim {
            $claim = LoyaltyRewardClaim::query()
                ->with('loyaltyTransaction')
                ->lockForUpdate()
                ->findOrFail($claim->getKey());

            if ($claim->status === LoyaltyClaimStatus::Rejected) {
                return $claim;
            }

            if (
                $claim->status !== LoyaltyClaimStatus::Pending
                || ! $claim->loyaltyTransaction
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Only a funded pending reward claim can be rejected.',
                ]);
            }

            $this->ledger->refund(
                $claim->loyaltyTransaction,
                "loyalty:claim-refund:{$claim->id}",
                $claim,
                "Refund for rejected reward claim {$claim->id}"
            );

            $claim->update([
                'status' => LoyaltyClaimStatus::Rejected,
                'processed_by' => $actor->getKey(),
                'processed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $claim->refresh();
        }, 3);
    }
}
