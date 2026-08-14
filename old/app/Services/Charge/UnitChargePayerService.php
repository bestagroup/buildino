<?php

namespace App\Services\Charge;

use App\Enums\UnitChargePayerSource;
use App\Models\Unit;
use App\Models\UnitChargeSetting;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Validation\ValidationException;

final class UnitChargePayerService
{
    public function __construct(
        private readonly WalletService $wallets
    ) {
    }

    public function configure(
        Unit $unit,
        UnitChargePayerSource $source,
        ?User $payerUser = null,
        bool $autoCollect = true,
        bool $allowPartial = true
    ): UnitChargeSetting {
        if ($source === UnitChargePayerSource::UserWallet) {
            if (! $payerUser) {
                throw ValidationException::withMessages([
                    'payer_user_id' => 'A payer user is required for user_wallet source.',
                ]);
            }

            if (! $this->isCurrentResidentOrOwner($unit, $payerUser)) {
                throw ValidationException::withMessages([
                    'payer_user_id' => 'The payer user must currently be an owner or occupant of this unit.',
                ]);
            }
        } else {
            $payerUser = null;
        }

        return UnitChargeSetting::query()->updateOrCreate(
            ['unit_id' => $unit->getKey()],
            [
                'payer_source' => $source,
                'payer_user_id' => $payerUser?->getKey(),
                'auto_collect' => $autoCollect,
                'allow_partial' => $allowPartial,
            ]
        );
    }

    public function resolveWallet(Unit $unit): Wallet
    {
        $setting = UnitChargeSetting::query()
            ->where('unit_id', $unit->getKey())
            ->first();

        if (
            ! $setting
            || $setting->payer_source === UnitChargePayerSource::UnitWallet
        ) {
            return $this->wallets->walletFor($unit);
        }

        $payer = $setting->payerUser;

        if (! $payer || ! $this->isCurrentResidentOrOwner($unit, $payer)) {
            throw ValidationException::withMessages([
                'payer_user_id' => 'Configured payer user is no longer an active owner or occupant of the unit.',
            ]);
        }

        return $this->wallets->walletFor($payer);
    }

    private function isCurrentResidentOrOwner(
        Unit $unit,
        User $user
    ): bool {
        $today = now()->toDateString();

        $occupancy = UnitOccupancy::query()
            ->where('unit_id', $unit->getKey())
            ->where('user_id', $user->getKey())
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->exists();

        if ($occupancy) {
            return true;
        }

        return UnitOwnership::query()
            ->where('unit_id', $unit->getKey())
            ->where('user_id', $user->getKey())
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->exists();
    }
}
