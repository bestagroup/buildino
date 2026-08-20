<?php

namespace Tests\Feature\Web;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Services\Web\ManagementHeaderContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ManagementDashboardHeaderUiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_header_context_exposes_personal_wallet_and_database_notifications(): void
    {
        $user =
            $this->createUser();

        $wallet =
            $user
                ->wallets()
                ->where(
                    'currency',
                    'IRR'
                )
                ->firstOrFail();

        $wallet->update([
            'balance' =>
                5_000_000,
            'locked_balance' =>
                1_250_000,
        ]);

        NotificationLog::query()
            ->create([
                'idempotency_key' =>
                    'header-unread-1',
                'notifiable_type' =>
                    $user->getMorphClass(),
                'notifiable_id' =>
                    $user->getKey(),
                'notification_type' =>
                    'dashboard.test',
                'channel' =>
                    NotificationChannel::Database,
                'provider' =>
                    'database',
                'title' =>
                    'اعلان تستی',
                'message' =>
                    'این اعلان باید در Header نمایش داده شود.',
                'status' =>
                    NotificationStatus::Delivered,
                'attempts' =>
                    1,
                'sent_at' =>
                    now(),
                'delivered_at' =>
                    now(),
            ]);

        NotificationLog::query()
            ->create([
                'idempotency_key' =>
                    'header-email-ignore',
                'notifiable_type' =>
                    $user->getMorphClass(),
                'notifiable_id' =>
                    $user->getKey(),
                'notification_type' =>
                    'dashboard.email',
                'channel' =>
                    NotificationChannel::Email,
                'provider' =>
                    'laravel-mail',
                'title' =>
                    'Email only',
                'message' =>
                    'این مورد نباید در Notification Center وب شمرده شود.',
                'status' =>
                    NotificationStatus::Sent,
                'attempts' =>
                    1,
                'sent_at' =>
                    now(),
            ]);

        $header =
            app(
                ManagementHeaderContextService::class
            )->context(
                $user
            );

        $this->assertSame(
            5_000_000,
            $header['wallet']['balance']
        );

        $this->assertSame(
            1_250_000,
            $header['wallet']['locked_balance']
        );

        $this->assertSame(
            3_750_000,
            $header['wallet']['available_balance']
        );

        $this->assertSame(
            1,
            $header['notifications']['unread_count']
        );

        $this->assertCount(
            1,
            $header['notifications']['items']
        );

        $this->assertSame(
            'اعلان تستی',
            $header[
                'notifications'
            ][
                'items'
            ][0]['title']
        );
    }

    public function test_dashboard_renders_wallet_notification_center_and_ui_libraries(): void
    {
        $graph =
            $this->createBuildingGraph();

        $user =
            $this->createUser();

        $this->grantPermission(
            $user->id,
            $graph['building'],
            'reports.dashboard.view'
        );

        $wallet =
            $user
                ->wallets()
                ->where(
                    'currency',
                    'IRR'
                )
                ->firstOrFail();

        $wallet->update([
            'balance' =>
                9_900_000,
            'locked_balance' =>
                900_000,
        ]);

        NotificationLog::query()
            ->create([
                'idempotency_key' =>
                    'header-render-1',
                'notifiable_type' =>
                    $user->getMorphClass(),
                'notifiable_id' =>
                    $user->getKey(),
                'notification_type' =>
                    'dashboard.render',
                'channel' =>
                    NotificationChannel::Database,
                'provider' =>
                    'database',
                'title' =>
                    'اعلان داشبورد',
                'message' =>
                    'متن اعلان داشبورد',
                'status' =>
                    NotificationStatus::Delivered,
                'attempts' =>
                    1,
                'sent_at' =>
                    now(),
                'delivered_at' =>
                    now(),
            ]);

        $this->actingAs(
            $user,
            'web'
        );

        $response =
            $this->get(
                '/management'
            );

        $response
            ->assertOk()
            ->assertSee(
                'موجودی قابل استفاده'
            )
            ->assertSee(
                'اعلان داشبورد'
            )
            ->assertSee(
                'کیف پول شخصی'
            )
            ->assertSee(
                config(
                    'management_ui.libraries.bootstrap.css'
                ),
                false
            )
            ->assertSee(
                config(
                    'management_ui.libraries.sweetalert2.js'
                ),
                false
            )
            ->assertSee(
                config(
                    'management_ui.libraries.jdate.js'
                ),
                false
            );
    }

    private function grantPermission(
        int $userId,
        $scope,
        string $permissionName
    ): void {
        $role =
            Role::query()
                ->create([
                    'name' =>
                        'header-ui-'
                        . uniqid(),
                    'display_name' =>
                        'Header UI',
                    'is_system' =>
                        false,
                ]);

        $permission =
            Permission::query()
                ->firstOrCreate(
                    [
                        'name' =>
                            $permissionName,
                    ],
                    [
                        'display_name' =>
                            $permissionName,
                        'module' =>
                            'reports',
                    ]
                );

        $role
            ->permissions()
            ->sync([
                $permission->id,
            ]);

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $userId,
                'role_id' =>
                    $role->id,
                'scope_type' =>
                    $scope
                        ?->getMorphClass(),
                'scope_id' =>
                    $scope
                        ?->getKey(),
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
}
