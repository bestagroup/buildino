<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementRoleDashboardWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_gets_platform_command_center_and_system_sections(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.superadmin@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'کنترل کل پلتفرم'
            )
            ->assertSee(
                'ثبت مجتمع'
            )
            ->assertSee(
                'کاربران فعال'
            )
            ->assertSee(
                'سلامت سامانه'
            )
            ->assertSee(
                'وضعیت رابط برنامه‌نویسی'
            );
    }

    public function test_complex_manager_gets_complex_kpis_without_system_internals(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.complex@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'داشبورد مدیر مجتمع'
            )
            ->assertSee(
                'ساختمان‌های تحت مدیریت'
            )
            ->assertSee(
                'مالک و ساکن'
            )
            ->assertDontSee(
                'سلامت سامانه'
            )
            ->assertDontSee(
                'وضعیت رابط برنامه‌نویسی'
            );
    }

    public function test_building_manager_gets_full_building_command_center_but_no_global_security_module(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.building@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'داشبورد مدیر ساختمان'
            )
            ->assertSee(
                'کیف پول ساختمان'
            )
            ->assertSee(
                'رزرو فعال'
            )
            ->assertSee(
                'تیکت جدید'
            )
            ->assertDontSee(
                'امنیت و کنترل'
            )
            ->assertDontSee(
                'سلامت سامانه'
            );
    }

    public function test_finance_manager_gets_finance_only_recent_activity(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.finance@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'مرکز مالی ساختمان'
            )
            ->assertSee(
                'خالص جریان نقد'
            )
            ->assertSee(
                'ثبت هزینه'
            )
            ->assertSee(
                'پرداخت‌های اخیر'
            )
            ->assertDontSee(
                'رزروهای اخیر'
            )
            ->assertDontSee(
                'تیکت‌های اخیر'
            );
    }

    public function test_operator_gets_operational_dashboard_without_finance_panels(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.operator@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'مرکز عملیات ساختمان'
            )
            ->assertSee(
                'مهمان‌های فعال'
            )
            ->assertSee(
                'رزرو فعال'
            )
            ->assertSee(
                'خدمت فعال'
            )
            ->assertDontSee(
                'تصویر مالی ساختمان'
            )
            ->assertDontSee(
                'سن مطالبات'
            )
            ->assertDontSee(
                'پرداخت‌های اخیر'
            );
    }

    public function test_support_agent_gets_support_workspace_without_financial_data(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $this->actingAs(
            $this->user(
                'role.support@buildino.local'
            ),
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'مرکز پشتیبانی و SLA'
            )
            ->assertSee(
                'تیکت فعال'
            )
            ->assertSee(
                'تیکت‌های اخیر'
            )
            ->assertSee(
                'درخواست‌های خدمت'
            )
            ->assertDontSee(
                'تصویر مالی ساختمان'
            )
            ->assertDontSee(
                'پرداخت‌های اخیر'
            );
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
}
