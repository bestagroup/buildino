<?php

namespace Database\Seeders;

use App\Enums\OccupancyType;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AccessScenarioSeeder extends Seeder
{
    private const PASSWORD =
        'Demo@1405';

    public function run(): void
    {
        if (
            ! app()->environment([
                'local',
                'testing',
            ])
        ) {
            throw new RuntimeException(
                'AccessScenarioSeeder is restricted to local/testing environments.'
            );
        }

        $this->call(
            RoleMatrixSeeder::class
        );

        $scenario =
            DB::transaction(
                fn (): array =>
                    $this->buildScenario()
            );

        $this->printSummary(
            $scenario
        );
    }

    private function buildScenario(): array
    {
        $primaryComplex =
            Complex::query()
                ->updateOrCreate(
                    [
                        'code' =>
                            'DEMO-COMPLEX-A',
                    ],
                    [
                        'title' =>
                            'مجتمع آزمایشی دسترسی',

                        'province' =>
                            'تهران',

                        'city' =>
                            'تهران',

                        'address' =>
                            'داده آزمایشی مخصوص تست Role و Scope',

                        'sort_order' =>
                            1,

                        'is_active' =>
                            true,
                    ]
                );

        $outsideComplex =
            Complex::query()
                ->updateOrCreate(
                    [
                        'code' =>
                            'DEMO-COMPLEX-OUTSIDE',
                    ],
                    [
                        'title' =>
                            'مجتمع خارج از محدوده',

                        'province' =>
                            'تهران',

                        'city' =>
                            'تهران',

                        'address' =>
                            'برای تست عدم دسترسی بین Scopeها',

                        'sort_order' =>
                            2,

                        'is_active' =>
                            true,
                    ]
                );

        $buildingA =
            $this->building(
                $primaryComplex,
                'DEMO-BUILDING-A',
                'ساختمان آلفا',
                'A'
            );

        $buildingB =
            $this->building(
                $primaryComplex,
                'DEMO-BUILDING-B',
                'ساختمان بتا',
                'B'
            );

        $buildingOutside =
            $this->building(
                $outsideComplex,
                'DEMO-BUILDING-C',
                'ساختمان گاما - خارج از محدوده',
                'C'
            );

        $unitsA =
            $this->structure(
                $buildingA,
                'A'
            );

        $this->structure(
            $buildingB,
            'B'
        );

        $this->structure(
            $buildingOutside,
            'C'
        );

        $users = [
            'superadmin' =>
                $this->user(
                    '09121110000',
                    'role.superadmin@buildino.local',
                    'مدیر',
                    'کل آزمایشی'
                ),

            'complex_manager' =>
                $this->user(
                    '09121110001',
                    'role.complex@buildino.local',
                    'مدیر',
                    'مجتمع'
                ),

            'building_manager' =>
                $this->user(
                    '09121110002',
                    'role.building@buildino.local',
                    'مدیر',
                    'ساختمان'
                ),

            'finance_manager' =>
                $this->user(
                    '09121110003',
                    'role.finance@buildino.local',
                    'مدیر',
                    'مالی'
                ),

            'operator' =>
                $this->user(
                    '09121110004',
                    'role.operator@buildino.local',
                    'اپراتور',
                    'ساختمان'
                ),

            'support_agent' =>
                $this->user(
                    '09121110005',
                    'role.support@buildino.local',
                    'کارشناس',
                    'پشتیبانی'
                ),

            'service_provider' =>
                $this->user(
                    '09121110006',
                    'role.provider@buildino.local',
                    'ارائه‌دهنده',
                    'خدمات'
                ),

            'owner' =>
                $this->user(
                    '09121110007',
                    'role.owner@buildino.local',
                    'مالک',
                    'آزمایشی'
                ),

            'tenant' =>
                $this->user(
                    '09121110008',
                    'role.tenant@buildino.local',
                    'مستأجر',
                    'آزمایشی'
                ),
        ];

        foreach ($users as $user) {
            UserRoleAssignment::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->delete();
        }

        $this->assign(
            $users['superadmin'],
            'superadmin'
        );

        $this->assign(
            $users[
                'complex_manager'
            ],
            'complex_manager',
            $primaryComplex
        );

        foreach (
            [
                'building_manager',
                'finance_manager',
                'operator',
                'support_agent',
                'service_provider',
            ]
            as $role
        ) {
            $this->assign(
                $users[$role],
                $role,
                $buildingA
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Resident personas
        |--------------------------------------------------------------------------
        |
        | These role assignments are classification-only. Owner/Tenant roles
        | intentionally contain no management permissions. Their real access
        | comes from UnitOwnership / UnitOccupancy.
        |
        */

        $this->assign(
            $users['owner'],
            'owner',
            $unitsA['101']
        );

        $this->assign(
            $users['tenant'],
            'tenant',
            $unitsA['102']
        );

        UnitOwnership::query()
            ->updateOrCreate(
                [
                    'unit_id' =>
                        $unitsA[
                            '101'
                        ]->getKey(),

                    'user_id' =>
                        $users[
                            'owner'
                        ]->getKey(),
                ],
                [
                    'ownership_percentage' =>
                        100,

                    'starts_at' =>
                        now()
                            ->subYear()
                            ->toDateString(),

                    'ends_at' =>
                        null,

                    'is_primary' =>
                        true,

                    'is_active' =>
                        true,

                    'created_by' =>
                        $users[
                            'superadmin'
                        ]->getKey(),

                    'notes' =>
                        'مالک آزمایشی سناریوی Role/Scope',
                ]
            );

        UnitOccupancy::query()
            ->updateOrCreate(
                [
                    'unit_id' =>
                        $unitsA[
                            '102'
                        ]->getKey(),

                    'user_id' =>
                        $users[
                            'tenant'
                        ]->getKey(),
                ],
                [
                    'occupancy_type' =>
                        OccupancyType::Tenant,

                    'starts_at' =>
                        now()
                            ->subMonths(3)
                            ->toDateString(),

                    'ends_at' =>
                        null,

                    'is_primary' =>
                        true,

                    'is_active' =>
                        true,

                    'created_by' =>
                        $users[
                            'superadmin'
                        ]->getKey(),

                    'notes' =>
                        'مستأجر آزمایشی سناریوی Role/Scope',
                ]
            );

        return [
            'complex' =>
                $primaryComplex,

            'outside_complex' =>
                $outsideComplex,

            'building_a' =>
                $buildingA,

            'building_b' =>
                $buildingB,

            'building_outside' =>
                $buildingOutside,

            'users' =>
                $users,
        ];
    }

    private function building(
        Complex $complex,
        string $code,
        string $title,
        string $number
    ): Building {
        return Building::query()
            ->updateOrCreate(
                [
                    'code' => $code,
                ],
                [
                    'complex_id' =>
                        $complex->getKey(),

                    'title' =>
                        $title,

                    'building_number' =>
                        $number,

                    'address' =>
                        'ساختمان آزمایشی سناریوی دسترسی',

                    'timezone' =>
                        'Asia/Tehran',

                    'currency' =>
                        'IRR',

                    'floors_count' =>
                        1,

                    'units_count' =>
                        2,

                    'parking_count' =>
                        0,

                    'storage_count' =>
                        0,

                    'is_active' =>
                        true,
                ]
            );
    }

    /**
     * @return array<string, Unit>
     */
    private function structure(
        Building $building,
        string $suffix
    ): array {
        $block =
            Block::query()
                ->updateOrCreate(
                    [
                        'building_id' =>
                            $building
                                ->getKey(),

                        'title' =>
                            "بلوک {$suffix}",
                    ],
                    [
                        'sort_order' =>
                            1,

                        'is_active' =>
                            true,
                    ]
                );

        $floor =
            Floor::query()
                ->updateOrCreate(
                    [
                        'block_id' =>
                            $block
                                ->getKey(),

                        'floor_number' =>
                            1,
                    ],
                    [
                        'title' =>
                            'طبقه اول',

                        'sort_order' =>
                            1,
                    ]
                );

        $units = [];

        foreach (
            [
                '101',
                '102',
            ]
            as $number
        ) {
            $units[$number] =
                Unit::query()
                    ->updateOrCreate(
                        [
                            'floor_id' =>
                                $floor
                                    ->getKey(),

                            'unit_number' =>
                                $number,
                        ],
                        [
                            'title' =>
                                "واحد {$number}",

                            'area' =>
                                $number
                                === '101'
                                    ? 110
                                    : 85,

                            'bedrooms' =>
                                2,

                            'usage_type' =>
                                'residential',

                            'is_active' =>
                                true,
                        ]
                    );
        }

        return $units;
    }

    private function user(
        string $mobile,
        string $email,
        string $firstName,
        string $lastName
    ): User {
        return User::query()
            ->updateOrCreate(
                [
                    'mobile' =>
                        $mobile,
                ],
                [
                    'first_name' =>
                        $firstName,

                    'last_name' =>
                        $lastName,

                    'email' =>
                        $email,

                    'mobile_verified_at' =>
                        now(),

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        Hash::make(
                            self::PASSWORD
                        ),

                    'is_active' =>
                        true,

                    'is_blocked' =>
                        false,
                ]
            );
    }

    private function assign(
        User $user,
        string $roleName,
        object|null $scope = null
    ): void {
        $role =
            Role::query()
                ->where(
                    'name',
                    $roleName
                )
                ->firstOrFail();

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $user->getKey(),

                'role_id' =>
                    $role->getKey(),

                'scope_type' =>
                    $scope
                        ? $scope
                            ->getMorphClass()
                        : null,

                'scope_id' =>
                    $scope
                        ? $scope
                            ->getKey()
                        : null,

                'starts_at' =>
                    now()
                        ->subMinute(),

                'ends_at' =>
                    null,

                'is_active' =>
                    true,

                'assigned_by' =>
                    null,
            ]);
    }

    private function printSummary(
        array $scenario
    ): void {
        if (! $this->command) {
            return;
        }

        $this->command->newLine();

        $this->command->info(
            'Buildino access scenario created successfully.'
        );

        $this->command->line(
            'Password for every scenario account: '
            . self::PASSWORD
        );

        $this->command->newLine();

        foreach (
            $scenario['users']
            as $role => $user
        ) {
            $this->command->line(
                sprintf(
                    '%-18s | %s | %s',
                    $role,
                    $user->mobile,
                    $user->email
                )
            );
        }

        $this->command->newLine();

        $this->command->line(
            'Complex manager scope: '
            . $scenario[
                'complex'
            ]->title
        );

        $this->command->line(
            'Building-scoped roles: '
            . $scenario[
                'building_a'
            ]->title
        );

        $this->command->line(
            'Same-complex comparison building: '
            . $scenario[
                'building_b'
            ]->title
        );

        $this->command->line(
            'Outside-scope control building: '
            . $scenario[
                'building_outside'
            ]->title
        );
    }
}
