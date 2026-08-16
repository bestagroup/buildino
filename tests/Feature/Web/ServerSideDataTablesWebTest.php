<?php

namespace Tests\Feature\Web;

use App\Enums\InvoiceStatus;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerSideDataTablesWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_yajra_datatables_package_is_installed(): void
    {
        $this->assertTrue(
            class_exists(
                \Yajra\DataTables\Facades\DataTables::class
            ),
            'Install Yajra first: composer require yajra/laravel-datatables-oracle:"^12.0"'
        );
    }

    public function test_yajra_datatables_service_is_registered(): void
    {
        $this->assertTrue(
            $this->app->bound('datatables'),
            'The Yajra datatables container binding is missing.'
        );

        $this->assertInstanceOf(
            \Yajra\DataTables\DataTables::class,
            $this->app->make('datatables')
        );
    }

    public function test_management_dashboard_renders_server_side_tables_instead_of_rows(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $admin =
            $this->user(
                'role.superadmin@buildino.local'
            );

        $this->actingAs(
            $admin,
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'management-payments-table',
                false
            )
            ->assertSee(
                'management-reservations-table',
                false
            )
            ->assertSee(
                'management-services-table',
                false
            )
            ->assertSee(
                'management-support-table',
                false
            )
            ->assertSee(
                route(
                    'management.datatables',
                    [
                        'table' =>
                            'payments',
                    ]
                ),
                false
            );
    }

    public function test_resident_invoice_datatable_returns_yajra_server_side_protocol_and_stays_unit_scoped(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $ownerUnit =
            $this->unit(
                '101'
            );

        $otherUnit =
            $this->unit(
                '102'
            );

        $ownerUnit->loadMissing(
            'floor.block.building'
        );

        $otherUnit->loadMissing(
            'floor.block.building'
        );

        UnitInvoice::query()
            ->create([
                'building_id' =>
                    $ownerUnit
                        ->floor
                        ->block
                        ->building
                        ->getKey(),

                'unit_id' =>
                    $ownerUnit->getKey(),

                'invoice_number' =>
                    'DT-OWNER-001',

                'issue_date' =>
                    now()->toDateString(),

                'due_date' =>
                    now()
                        ->addDays(5)
                        ->toDateString(),

                'subtotal' =>
                    1_000_000,

                'total_amount' =>
                    1_000_000,

                'paid_amount' =>
                    0,

                'outstanding_amount' =>
                    1_000_000,

                'status' =>
                    InvoiceStatus::Issued,

                'created_by' =>
                    $owner->getKey(),
            ]);

        UnitInvoice::query()
            ->create([
                'building_id' =>
                    $otherUnit
                        ->floor
                        ->block
                        ->building
                        ->getKey(),

                'unit_id' =>
                    $otherUnit->getKey(),

                'invoice_number' =>
                    'DT-OTHER-001',

                'issue_date' =>
                    now()->toDateString(),

                'due_date' =>
                    now()
                        ->addDays(5)
                        ->toDateString(),

                'subtotal' =>
                    2_000_000,

                'total_amount' =>
                    2_000_000,

                'paid_amount' =>
                    0,

                'outstanding_amount' =>
                    2_000_000,

                'status' =>
                    InvoiceStatus::Issued,

                'created_by' =>
                    $owner->getKey(),
            ]);

        $this->actingAs(
            $owner,
            'web'
        );

        $response =
            $this->getJson(
                route(
                    'portal.resident.datatables',
                    [
                        'table' =>
                            'invoices',
                    ]
                )
                . '?'
                . http_build_query(
                    $this->dataTableQuery(
                        [
                            'invoice_number',
                            'unit_title',
                            'total_amount_formatted',
                            'outstanding_amount_formatted',
                            'due_date_jalali',
                            'status_label',
                            'action_url',
                        ]
                    )
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'draw',
                1
            )
            ->assertJsonPath(
                'recordsTotal',
                1
            );

        $numbers =
            collect(
                $response->json(
                    'data',
                    []
                )
            )
                ->pluck(
                    'invoice_number'
                )
                ->all();

        $this->assertContains(
            'DT-OWNER-001',
            $numbers
        );

        $this->assertNotContains(
            'DT-OTHER-001',
            $numbers
        );
    }

    public function test_provider_service_datatable_is_assignment_scoped(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $this->actingAs(
            $provider,
            'web'
        );

        $this->getJson(
            route(
                'portal.provider.datatables',
                [
                    'table' =>
                        'services',
                ]
            )
            . '?'
            . http_build_query(
                $this->dataTableQuery(
                    [
                        'request_number',
                        'title',
                        'building_title',
                        'unit_title',
                        'payment_status_label',
                        'status_label',
                        'action_url',
                    ]
                )
            )
        )
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
    }

    /**
     * @param array<int, string> $columns
     */
    private function dataTableQuery(
        array $columns
    ): array {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => [
                'value' => '',
                'regex' => 'false',
            ],
            'columns' =>
                collect(
                    $columns
                )
                    ->map(
                        fn (
                            string $column
                        ): array => [
                            'data' =>
                                $column,
                            'name' =>
                                $column,
                            'searchable' =>
                                'true',
                            'orderable' =>
                                'false',
                            'search' => [
                                'value' =>
                                    '',
                                'regex' =>
                                    'false',
                            ],
                        ]
                    )
                    ->all(),
        ];
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }

    private function unit(
        string $number
    ): Unit {
        return Unit::query()
            ->where(
                'unit_number',
                $number
            )
            ->firstOrFail();
    }
}
