<?php

namespace Tests\Feature\Web;

use App\Models\Unit;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\Wallet\WalletService;
use App\Services\Web\PortalAccessService;
use App\Services\Web\ResidentWalletHeaderService;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentWalletHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_tenant_headers_display_unit_wallet_and_refresh_after_balance_changes(): void
    {
        $this->seed(AccessScenarioSeeder::class);
        foreach (['owner', 'tenant'] as $role) {
            $user = User::where('email', 'role.'.$role.'@buildino.local')->firstOrFail();
            $unit = app(PortalAccessService::class)->residentUnits($user)->firstOrFail();
            $wallet = app(WalletService::class)->walletFor($unit);
            $wallet->update(['balance' => 25_000_000, 'locked_balance' => 1_000_000]);
            $personal = app(WalletService::class)->walletFor($user);
            $personal->update(['balance' => 789]);
            $context = app(ResidentWalletHeaderService::class)->context($user, $unit->id);
            $this->assertSame(24_000_000, $context['selected']['available_balance']);
            $this->assertSame($unit->id, $context['selected']['unit_id']);
            $response = $this->actingAs($user)->get('/portal/resident?unit_id='.$unit->id)->assertOk();
            preg_match('/<button[^>]+class="portal-wallet-chip".*?<\/button>/s', $response->getContent(), $chip);
            $this->assertStringContainsString('24,000,000', $chip[0]);
            $this->assertStringNotContainsString('موجودی شخصی', $chip[0]);
            $wallet->update(['balance' => 30_000_000]);
            $this->assertSame(29_000_000, app(ResidentWalletHeaderService::class)->context($user, $unit->id)['selected']['available_balance']);
            $wallet->update(['is_active' => false]);
            $this->assertSame(0, app(ResidentWalletHeaderService::class)->context($user, $unit->id)['selected']['available_balance']);
            $wallet->update(['is_active' => true]);
        }
    }

    public function test_multiple_units_are_separate_and_foreign_wallet_is_never_exposed(): void
    {
        $this->seed(AccessScenarioSeeder::class);
        $user = User::where('email', 'role.owner@buildino.local')->firstOrFail();
        $ownership = UnitOwnership::where('user_id', $user->id)->firstOrFail();
        $service = app(ResidentWalletHeaderService::class);
        $this->assertSame($ownership->unit_id, $service->context($user)['selected']['unit_id']);
        $other = Unit::where('id', '!=', $ownership->unit_id)->firstOrFail();
        $denied = $service->context($user, $other->id);
        $this->assertNull($denied['selected']);
        $this->assertNotContains($other->id, array_column($denied['wallets'], 'unit_id'));
        $link = $ownership->replicate();
        $link->unit_id = $other->id;
        $link->save();
        $building = $other->floor->block->building;
        $building->update(['currency' => 'IRT']);
        app(WalletService::class)->walletFor($other, 'IRT')->update(['balance' => 25000]);
        $context = $service->context($user);
        $this->assertNull($context['selected']);
        $this->assertCount(2, $context['wallets']);
        $selected = $service->context($user, $other->id)['selected'];
        $this->assertSame('IRT', $selected['currency']);
        $this->assertSame(25000, $selected['available_balance']);
        $this->actingAs($user)->get('/portal/resident')->assertOk()->assertSee('انتخاب واحد');
        $user->update(['is_blocked' => true]);
        $this->assertSame([], $service->context($user)['wallets']);
    }
}
