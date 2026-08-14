<?php

namespace App\Services\Wallet;

use App\Models\Building;
use App\Models\PlatformWalletAccount;
use App\Models\Unit;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Security\UnitResidentAccessService;
use App\Support\Authorization\PermissionChecker;

final class WalletAccessService
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly UnitResidentAccessService $residentAccess
    ) {
    }

    public function canView(
        User $user,
        Wallet $wallet
    ): bool {
        if (! $user->is_active || $user->is_blocked) {
            return false;
        }

        $wallet->loadMissing('owner');

        $owner = $wallet->owner;

        if ($owner instanceof User) {
            if ((int) $owner->getKey() === (int) $user->getKey()) {
                return true;
            }

            /*
             * Viewing another user's personal wallet is intentionally
             * limited to a GLOBAL wallets.view assignment.
             */
            return $this->permissions->allows(
                $user,
                'wallets.view',
                null
            );
        }

        if ($owner instanceof Unit) {
            if ($this->residentAccess->allows($user, $owner)) {
                return true;
            }

            $owner->loadMissing('floor.block.building');

            $building = $owner->floor?->block?->building;

            return $building
                ? $this->permissions->allows(
                    $user,
                    'wallets.view',
                    $building
                )
                : false;
        }

        if ($owner instanceof Building) {
            return $this->permissions->allows(
                $user,
                'building-wallet.view',
                $owner
            );
        }

        if ($owner instanceof PlatformWalletAccount) {
            return $this->permissions->allows(
                $user,
                'platform-wallet.view',
                null
            );
        }

        return false;
    }
}
