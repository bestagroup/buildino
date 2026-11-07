<?php

namespace Tests\Feature\Financial;

use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\Wallet\WalletAccessService;
use App\Services\Wallet\WalletService;
use App\Services\Web\PortalAccessService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class MultiUnitWalletAccessTest extends TestCase
{
    use CreatesBuildingDomainData, RefreshDatabase;

    public function test_manager_can_also_own_a_unit_without_merging_wallets(): void
    {
        $this->seed(AccessScenarioSeeder::class);
        $manager = User::where('email', 'role.building@buildino.local')->firstOrFail();
        $owner = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $unit = UnitOwnership::where('user_id', $owner->id)->firstOrFail()->unit;
        UnitOwnership::create(['unit_id' => $unit->id, 'user_id' => $manager->id, 'starts_at' => today(), 'is_active' => true]);
        $wallets = app(WalletService::class);
        $access = app(WalletAccessService::class);
        $ids = [];
        foreach ([$manager, $unit, $unit->floor->block->building] as $entity) {
            $wallet = $wallets->walletFor($entity);
            $ids[] = $wallet->id;
            $this->assertTrue($access->canView($manager, $wallet));
        }
        $this->assertCount(3, array_unique($ids));
        $this->assertFalse($access->canView($manager, $wallets->walletFor($owner)));
    }

    public function test_multiple_units_across_buildings_share_identity_but_not_wallets(): void
    {
        $user = $this->createUser();
        $first = $this->createBuildingGraph();
        $second = $this->createBuildingGraph();
        $thirdUnit = $first['unit']->replicate();
        $thirdUnit->unit_number = 'extra';
        $thirdUnit->save();
        $units = [$first['unit'], $second['unit'], $thirdUnit];
        $wallets = app(WalletService::class);
        $access = app(WalletAccessService::class);
        $ids = [$wallets->walletFor($user)->id, $wallets->walletFor($first['building'])->id, $wallets->walletFor($second['building'])->id];
        foreach ($units as $unit) {
            $relation = UnitOwnership::create(['unit_id' => $unit->id, 'user_id' => $user->id, 'starts_at' => today(), 'is_active' => true]);
            $wallet = $wallets->walletFor($unit);
            $ids[] = $wallet->id;
            $this->assertTrue($access->canView($user, $wallet));
            $this->assertFalse($access->canView($this->createUser(), $wallet));
        }
        $this->assertCount(6, array_unique($ids));
        $this->assertCount(3, app(PortalAccessService::class)->residentUnits($user));
        $relation->update(['is_active' => false]);
        $this->assertFalse($access->canView($user, $wallet));
        $this->assertSame($wallet->id, $wallets->walletFor($thirdUnit)->id);
        $this->assertCount(2, app(PortalAccessService::class)->residentUnits($user));
        $this->assertFalse($access->canView($user, $wallets->walletFor($first['building'])));
    }
}
