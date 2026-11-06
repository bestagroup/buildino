<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalManagementShortcutTest extends TestCase
{
    use RefreshDatabase;

    public static function accessCases(): array
    {
        return [
            'ordinary owner' => ['owner', null, false, false],
            'ordinary tenant' => ['tenant', null, false, false],
            'owner with building management' => ['owner', 'building', false, true],
            'tenant with platform management' => ['tenant', 'superadmin', false, true],
            'expired management assignment' => ['owner', 'building', true, false],
        ];
    }

    #[DataProvider('accessCases')]
    public function test_shortcut_matches_management_access(string $persona, ?string $manager, bool $expired, bool $visible): void
    {
        $this->seed(AccessScenarioSeeder::class);
        $user = User::where('email', "role.{$persona}@buildino.local")->firstOrFail();

        if ($manager !== null) {
            $managerUser = User::where('email', "role.{$manager}@buildino.local")->firstOrFail();
            $assignment = UserRoleAssignment::where('user_id', $managerUser->id)->firstOrFail()->replicate();
            $assignment->user_id = $user->id;
            $assignment->ends_at = $expired ? now()->subSecond() : null;
            $assignment->save();
        }

        $this->actingAs($user, 'web');
        $response = $this->get(route('portal.resident.dashboard'))->assertOk();

        if ($visible) {
            $response->assertSee('data-management-dashboard-link', false)
                ->assertSee('href="'.route('management.dashboard').'"', false)
                ->assertSeeInOrder(['data-management-dashboard-link', 'خروج از حساب'], false);
            $this->get(route('management.dashboard'))->assertOk();
        } else {
            $response->assertDontSee('data-management-dashboard-link', false);
            $this->get(route('management.dashboard'))->assertRedirect(route('login'));
        }
    }
}
