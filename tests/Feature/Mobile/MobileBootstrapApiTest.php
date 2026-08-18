<?php

namespace Tests\Feature\Mobile;

use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/app/bootstrap';

    public function test_unauthenticated_request_uses_standard_error_contract(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertExactJson([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required.',
            ]);
    }

    public function test_unverified_identity_is_rejected(): void
    {
        $user = User::factory()->create([
            'mobile_verified_at' => null,
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user, ['api']);

        $this->getJson(self::ENDPOINT)
            ->assertForbidden()
            ->assertJsonPath('code', 'IDENTITY_VERIFICATION_REQUIRED');
    }

    public function test_inactive_account_is_rejected_with_account_error(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        Sanctum::actingAs($user, ['api']);

        $this->getJson(self::ENDPOINT)
            ->assertForbidden()
            ->assertJsonPath('code', 'AUTH_ACCOUNT_NOT_ALLOWED');
    }

    public function test_owner_receives_one_owner_context(): void
    {
        [$user, $unit] = $this->residentAndUnit();
        $this->ownership($user, $unit);

        $response = $this->bootstrapAs($user)->assertOk();

        $response
            ->assertJsonPath('data.user.id', $user->getKey())
            ->assertJsonPath('data.personas.0', 'owner')
            ->assertJsonPath('data.contexts.0.id', 'unit-'.$unit->getKey())
            ->assertJsonPath('data.contexts.0.relationships.owner', true)
            ->assertJsonPath('data.contexts.0.relationships.occupant', false);

        $this->assertSame([
            'charges.view' => true,
            'wallet.view' => true,
        ], $response->json('data.contexts.0.capabilities'));
    }

    public function test_occupant_receives_one_occupant_context(): void
    {
        [$user, $unit] = $this->residentAndUnit();
        $this->occupancy($user, $unit);

        $this->bootstrapAs($user)
            ->assertOk()
            ->assertJsonPath('data.personas.0', 'occupant')
            ->assertJsonPath('data.contexts.0.relationships.owner', false)
            ->assertJsonPath('data.contexts.0.relationships.occupant', true);
    }

    public function test_owner_and_occupant_relationships_are_merged_per_unit(): void
    {
        [$user, $unit] = $this->residentAndUnit();
        $this->ownership($user, $unit);
        $this->occupancy($user, $unit);

        $response = $this->bootstrapAs($user)->assertOk();

        $this->assertCount(1, $response->json('data.contexts'));
        $response
            ->assertJsonPath('data.personas.0', 'owner')
            ->assertJsonPath('data.personas.1', 'occupant')
            ->assertJsonPath('data.contexts.0.relationships.owner', true)
            ->assertJsonPath('data.contexts.0.relationships.occupant', true);
    }

    public function test_multiple_units_produce_multiple_deterministically_ordered_contexts(): void
    {
        $user = User::factory()->create();
        $first = Unit::factory()->create();
        $second = Unit::factory()->create();
        $this->ownership($user, $second);
        $this->occupancy($user, $first);

        $response = $this->bootstrapAs($user)->assertOk();

        $this->assertCount(2, $response->json('data.contexts'));
        $ids = collect($response->json('data.contexts'))->pluck('id')->all();
        $this->assertSame(['unit-'.$first->id, 'unit-'.$second->id], $ids);
        $this->assertSame($ids[0], $response->json('data.suggested_context'));
    }

    public function test_unrelated_resident_relationships_are_never_exposed(): void
    {
        [$user, $ownUnit] = $this->residentAndUnit();
        $other = User::factory()->create();
        $otherUnit = Unit::factory()->create();
        $this->ownership($user, $ownUnit);
        $this->ownership($other, $otherUnit);
        $this->occupancy($other, $ownUnit);

        $response = $this->bootstrapAs($user)->assertOk();
        $this->assertSame(
            ['unit-'.$ownUnit->id],
            collect($response->json('data.contexts'))->pluck('id')->all()
        );
        $response->assertJsonPath('data.contexts.0.relationships.occupant', false);
    }

    public function test_inactive_and_expired_ownerships_are_excluded(): void
    {
        $user = User::factory()->create();
        $inactive = Unit::factory()->create();
        $expired = Unit::factory()->create();
        $this->ownership($user, $inactive, ['is_active' => false]);
        $this->ownership($user, $expired, ['ends_at' => now()->subDay()]);

        $this->bootstrapAs($user)
            ->assertOk()
            ->assertJsonCount(0, 'data.contexts');
    }

    public function test_inactive_and_expired_occupancies_are_excluded(): void
    {
        $user = User::factory()->create();
        $inactive = Unit::factory()->create();
        $expired = Unit::factory()->create();
        $this->occupancy($user, $inactive, ['is_active' => false]);
        $this->occupancy($user, $expired, ['ends_at' => now()->subDay()]);

        $this->bootstrapAs($user)
            ->assertOk()
            ->assertJsonCount(0, 'data.contexts');
    }

    public function test_zero_contexts_is_successful_and_has_null_suggestion(): void
    {
        $user = User::factory()->create();

        $this->bootstrapAs($user)
            ->assertOk()
            ->assertJsonPath('data.personas', [])
            ->assertJsonPath('data.contexts', [])
            ->assertJsonPath('data.suggested_context', null);
    }

    public function test_management_role_alone_does_not_create_personas_or_contexts(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'is_system' => true,
        ]);
        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->bootstrapAs($user)
            ->assertOk()
            ->assertJsonPath('data.personas', [])
            ->assertJsonPath('data.contexts', []);
    }

    private function bootstrapAs(User $user)
    {
        Sanctum::actingAs($user, ['api']);

        return $this->getJson(self::ENDPOINT);
    }

    private function residentAndUnit(): array
    {
        return [User::factory()->create(), Unit::factory()->create()];
    }

    private function ownership(User $user, Unit $unit, array $overrides = []): UnitOwnership
    {
        return UnitOwnership::query()->create(array_merge([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'ownership_percentage' => 100,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
            'is_primary' => true,
            'is_active' => true,
        ], $overrides));
    }

    private function occupancy(User $user, Unit $unit, array $overrides = []): UnitOccupancy
    {
        return UnitOccupancy::query()->create(array_merge([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'occupancy_type' => 'tenant',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
            'is_primary' => true,
            'is_active' => true,
        ], $overrides));
    }
}
