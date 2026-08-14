<?php

namespace Tests\Feature\Security;

use App\Enums\FacilityType;
use App\Enums\OccupancyType;
use App\Enums\ReservationStatus;
use App\Enums\UnitUsageType;
use App\Events\FacilityReservationApproved;
use App\Events\FacilityReservationCreated;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\FacilityReservation;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityReservationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            FacilityReservationCreated::class,
            FacilityReservationApproved::class,
        ]);
    }

    public function test_scoped_manager_can_configure_facility_only_in_assigned_building(): void
    {
        $manager = $this->createUser(
            '09123330001',
            'facility-manager@example.test'
        );

        $structureA = $this->createStructure('FAC-A');
        $structureB = $this->createStructure('FAC-B');

        $role = $this->createRoleWithPermissions(
            'facility-manager',
            [
                'facilities.view',
                'facilities.create',
                'facilities.update',
                'facilities.delete',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structureA['building']
        );

        Sanctum::actingAs($manager);

        $facilityResponse = $this->postJson(
            "/api/v1/buildings/{$structureA['building']->id}/facilities",
            [
                'title' => 'Gym A',
                'code' => 'GYM-A',
                'type' => FacilityType::Gym->value,
                'capacity' => 20,
                'default_price' => 50000,
                'requires_payment' => false,
                'requires_approval' => true,
                'is_active' => true,

                // Must be ignored; building comes from route.
                'building_id' => $structureB['building']->id,
            ]
        );

        $facilityResponse
            ->assertCreated()
            ->assertJsonPath(
                'data.building_id',
                $structureA['building']->id
            );

        $facilityId = $facilityResponse->json('data.id');

        $day = now()->addDay()->dayOfWeek;

        $schedule = $this->postJson(
            "/api/v1/facilities/{$facilityId}/schedules",
            [
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '22:00',
                'is_active' => true,
            ]
        );

        $schedule->assertCreated();

        $this->putJson(
            "/api/v1/facilities/{$facilityId}/reservation-rule",
            [
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 120,
                'min_advance_minutes' => 0,
                'max_advance_days' => 30,
                'max_reservations_per_day' => 3,
                'max_reservations_per_week' => 5,
                'max_reservations_per_month' => 10,
                'max_reservation_per_unit' => 10,
                'cancel_before_minutes' => 60,
                'cancellation_fee' => 0,
                'refund_percentage' => 100,
                'allow_guest' => false,
                'auto_confirm' => false,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.max_duration_minutes',
                120
            );

        $this->postJson(
            "/api/v1/buildings/{$structureB['building']->id}/facilities",
            [
                'title' => 'Denied Facility',
                'code' => 'DENIED',
                'type' => FacilityType::Other->value,
            ]
        )->assertForbidden();
    }

    public function test_resident_can_view_active_facility_and_reserve_only_own_unit_with_server_price(): void
    {
        $resident = $this->createUser(
            '09123331001',
            'facility-resident@example.test'
        );

        $structure = $this->createStructure('RESERVE');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $otherUnit = Unit::query()->create([
            'floor_id' => $structure['floor']->id,
            'unit_number' => '202-RESERVE',
            'title' => 'Other Unit',
            'area' => 80,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        $facility = $this->createFacility(
            $structure['building'],
            'RES-GYM',
            false
        );

        $tomorrow = now()->addDay();

        $schedule = $facility->facilitySchedules()->create([
            'day_of_week' => $tomorrow->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        $slot = $schedule->facilityTimeSlots()->create([
            'start_time' => '10:00',
            'end_time' => '11:00',
            'capacity' => 1,
            'price' => 75000,
            'is_active' => true,
        ]);

        $facility->facilityReservationRules()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 120,
            'min_advance_minutes' => 0,
            'max_advance_days' => 30,
            'max_reservations_per_day' => 3,
            'max_reservations_per_week' => 5,
            'max_reservations_per_month' => 10,
            'max_reservation_per_unit' => 10,
            'cancel_before_minutes' => 60,
            'cancellation_fee' => 0,
            'refund_percentage' => 100,
            'allow_guest' => false,
            'auto_confirm' => false,
        ]);

        Sanctum::actingAs($resident);

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/schedules",
            [
                'day_of_week' => now()->addDays(2)->dayOfWeek,
                'start_time' => '08:00',
                'end_time' => '09:00',
            ]
        )->assertForbidden();

        $this->getJson(
            "/api/v1/buildings/{$structure['building']->id}/facilities"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $facility->id
            );

        $response = $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'facility_time_slot_id' => $slot->id,
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),

                // These fields are intentionally not accepted by the request.
                'user_id' => 999999,
                'price' => 1,
                'final_amount' => 1,
                'status' => ReservationStatus::Approved->value,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user_id',
                $resident->id
            )
            ->assertJsonPath(
                'data.price',
                75000
            )
            ->assertJsonPath(
                'data.final_amount',
                75000
            )
            ->assertJsonPath(
                'data.status',
                ReservationStatus::Pending->value
            );

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'facility_time_slot_id' => $slot->id,
                'unit_id' => $otherUnit->id,
                'reservation_date' => $tomorrow->toDateString(),
            ]
        )->assertForbidden();
    }

    public function test_blackout_duration_schedule_and_capacity_rules_are_enforced(): void
    {
        $resident = $this->createUser(
            '09123332001',
            'facility-rules@example.test'
        );

        $structure = $this->createStructure('RULES');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $facility = $this->createFacility(
            $structure['building'],
            'RULE-GYM',
            false
        );

        $tomorrow = now()->addDay();

        $schedule = $facility->facilitySchedules()->create([
            'day_of_week' => $tomorrow->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        $slot = $schedule->facilityTimeSlots()->create([
            'start_time' => '12:00',
            'end_time' => '13:00',
            'capacity' => 1,
            'price' => 10000,
            'is_active' => true,
        ]);

        $facility->facilityReservationRules()->create([
            'min_duration_minutes' => 30,
            'max_duration_minutes' => 60,
            'min_advance_minutes' => 0,
            'max_advance_days' => 30,
            'max_reservation_per_unit' => 10,
            'cancel_before_minutes' => 0,
            'cancellation_fee' => 0,
            'refund_percentage' => 100,
            'allow_guest' => false,
            'auto_confirm' => false,
        ]);

        Sanctum::actingAs($resident);

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
                'start_time' => '08:00',
                'end_time' => '09:00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
                'start_time' => '10:00',
                'end_time' => '12:00',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');

        $facility->facilityBlackouts()->create([
            'starts_at' => $tomorrow->copy()->setTime(12, 0),
            'ends_at' => $tomorrow->copy()->setTime(13, 0),
            'reason' => 'Maintenance',
        ]);

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'facility_time_slot_id' => $slot->id,
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reservation_date');

        $facility->facilityBlackouts()->delete();

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'facility_time_slot_id' => $slot->id,
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
            ]
        )->assertCreated();

        $secondResident = $this->createUser(
            '09123332002',
            'second-resident@example.test'
        );

        $secondUnit = Unit::query()->create([
            'floor_id' => $structure['floor']->id,
            'unit_number' => '303-RULES',
            'title' => 'Second Unit',
            'area' => 70,
            'bedrooms' => 1,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        UnitOccupancy::query()->create([
            'unit_id' => $secondUnit->id,
            'user_id' => $secondResident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($secondResident);

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'facility_time_slot_id' => $slot->id,
                'unit_id' => $secondUnit->id,
                'reservation_date' => $tomorrow->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reservation_date');
    }

    public function test_manager_can_approve_pending_reservation_and_owner_can_cancel_it(): void
    {
        $manager = $this->createUser(
            '09123333001',
            'reservation-manager@example.test'
        );

        $resident = $this->createUser(
            '09123333002',
            'reservation-owner@example.test'
        );

        $structure = $this->createStructure('APPROVE');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $role = $this->createRoleWithPermissions(
            'reservation-manager',
            [
                'facility-reservations.view',
                'facility-reservations.approve',
                'facility-reservations.cancel',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        $facility = $this->createFacility(
            $structure['building'],
            'APP-GYM',
            true
        );

        $tomorrow = now()->addDay();

        $facility->facilitySchedules()->create([
            'day_of_week' => $tomorrow->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        $facility->facilityReservationRules()->create([
            'max_duration_minutes' => 120,
            'min_advance_minutes' => 0,
            'max_reservation_per_unit' => 10,
            'cancel_before_minutes' => 60,
            'cancellation_fee' => 0,
            'refund_percentage' => 100,
            'auto_confirm' => true,
        ]);

        Sanctum::actingAs($resident);

        $create = $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
                'start_time' => '14:00',
                'end_time' => '15:00',
            ]
        );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                ReservationStatus::Pending->value
            );

        $reservationId = $create->json('data.id');

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/facility-reservations/{$reservationId}/approve"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ReservationStatus::Approved->value
            )
            ->assertJsonPath(
                'data.approved_by',
                $manager->id
            );

        Sanctum::actingAs($resident);

        $this->postJson(
            "/api/v1/facility-reservations/{$reservationId}/cancel",
            [
                'reason' => 'Plans changed',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                ReservationStatus::Cancelled->value
            );

        $this->assertDatabaseHas(
            'reservation_cancellations',
            [
                'facility_reservation_id' => $reservationId,
                'cancelled_by' => $resident->id,
                'reason' => 'Plans changed',
            ]
        );
    }

    public function test_auto_confirm_rule_still_approves_when_facility_does_not_require_manual_approval(): void
    {
        $resident = $this->createUser(
            '09123334001',
            'auto-confirm-resident@example.test'
        );

        $structure = $this->createStructure('AUTO');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $facility = $this->createFacility(
            $structure['building'],
            'AUTO-GYM',
            false
        );

        $tomorrow = now()->addDay();

        $facility->facilityReservationRules()->create([
            'max_duration_minutes' => 120,
            'min_advance_minutes' => 0,
            'max_reservation_per_unit' => 10,
            'cancel_before_minutes' => 0,
            'cancellation_fee' => 0,
            'refund_percentage' => 100,
            'auto_confirm' => true,
        ]);

        Sanctum::actingAs($resident);

        $this->postJson(
            "/api/v1/facilities/{$facility->id}/reservations",
            [
                'unit_id' => $structure['unit']->id,
                'reservation_date' => $tomorrow->toDateString(),
                'start_time' => '16:00',
                'end_time' => '17:00',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                ReservationStatus::Approved->value
            );
    }

    private function createFacility(
        Building $building,
        string $code,
        bool $requiresApproval
    ) {
        return $building->buildingFacilities()->create([
            'title' => "Facility {$code}",
            'code' => $code,
            'type' => FacilityType::Gym->value,
            'capacity' => 20,
            'default_price' => 50000,
            'requires_payment' => false,
            'requires_approval' => $requiresApproval,
            'is_active' => true,
        ]);
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Facility',
            'last_name' => 'Tester',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createStructure(
        string $suffix
    ): array {
        $complex = Complex::query()->create([
            'code' => "CMP-{$suffix}",
            'title' => "Complex {$suffix}",
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => "BLD-{$suffix}",
            'title' => "Building {$suffix}",
            'timezone' => config('app.timezone'),
            'is_active' => true,
        ]);

        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => "Block {$suffix}",
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => "Floor {$suffix}",
            'sort_order' => 1,
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => "101-{$suffix}",
            'title' => "Unit {$suffix}",
            'area' => 100,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        return compact(
            'complex',
            'building',
            'block',
            'floor',
            'unit'
        );
    }

    private function createRoleWithPermissions(
        string $name,
        array $permissionNames
    ): Role {
        $role = Role::query()->create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => true,
        ]);

        foreach ($permissionNames as $permissionName) {
            $module = explode('.', $permissionName)[0];

            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                [
                    'display_name' => $permissionName,
                    'module' => $module,
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
