<?php

namespace App\Services\Web;

use App\Models\User;

final class ResidentWalletHeaderService
{
    public function __construct(private readonly PortalAccessService $access) {}

    public function context(User $user, ?int $unitId = null): array
    {
        $wallets = $this->access->residentUnits($user)->map(function ($unit): array {
            $building = $unit->floor?->block?->building;
            $currency = $building?->currency ?: 'IRR';
            $wallet = $unit->wallets->firstWhere('currency', $currency);

            return [
                'unit_id' => (int) $unit->id,
                'label' => ($building?->title ?? '').' / واحد '.$unit->unit_number,
                'available_balance' => $wallet && $wallet->is_active ? $wallet->availableBalance() : 0,
                'currency' => $currency,
            ];
        })->values();

        // Never substitute another unit for an explicitly requested, inaccessible unit.
        $selected = $unitId !== null
            ? $wallets->firstWhere('unit_id', $unitId)
            : ($wallets->count() === 1 ? $wallets->first() : null);

        return ['selected' => $selected, 'wallets' => $wallets->all()];
    }
}
