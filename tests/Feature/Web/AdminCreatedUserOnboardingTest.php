<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCreatedUserOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_does_not_supply_password_and_all_created_accounts_require_rotation(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager, 'web');

        $this->get('/management/operations/users')
            ->assertOk()
            ->assertSee('user-registration-dialog')
            ->assertSee('buildino-user-dialog.css');

        $fields = collect(config('management_crud.resources.users.fields'));
        $this->assertFalse($fields->contains('name', 'password'));

        $created = $this->postJson('/management/data/users', [
            'first_name' => 'کاربر',
            'last_name' => 'جدید',
            'mobile' => '09129998877',
            'email' => 'newuser@buildino.local',
            'verify_mobile' => true,
        ])->assertCreated();

        $user = User::findOrFail((int) $created->json('data.id'));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('123456789', $user->password));
        $this->assertNotSame('123456789', $user->password);
        $this->assertArrayNotHasKey('password', $created->json('data'));
    }

    public function test_manager_cannot_override_the_initial_password(): void
    {
        $this->actingAs($this->manager(), 'web');
        $created = $this->postJson('/management/data/users', [
            'first_name' => 'New',
            'last_name' => 'User',
            'mobile' => '09129998876',
            'password' => 'CustomPassword123',
        ])->assertCreated();

        $user = User::findOrFail((int) $created->json('data.id'));
        $this->assertTrue(Hash::check('123456789', $user->password));
        $this->assertFalse(Hash::check('CustomPassword123', $user->password));
    }

    public function test_first_login_requires_password_change_before_access_to_web_or_api(): void
    {
        $user = $this->manager([
            'must_change_password' => true,
            'password' => '123456789',
        ]);

        $this->actingAs($user, 'web');

        $this->get('/management/operations/users')
            ->assertRedirect('/account/change-initial-password');
        $this->getJson('/management/data/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->getJson('/api/v1/complexes')
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->get('/account/change-initial-password')
            ->assertOk()
            ->assertSee('تعیین رمز عبور شخصی');

        $this->post('/account/change-initial-password', [
            'current_password' => 'wrong-password',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertSessionHasErrors('current_password');

        $this->post('/account/change-initial-password', [
            'current_password' => '123456789',
            'password' => '123456789',
            'password_confirmation' => '123456789',
        ])->assertSessionHasErrors('password');

        $this->post('/account/change-initial-password', [
            'current_password' => '123456789',
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ])->assertRedirect('/management');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewSecurePass123', $user->password));
        $this->assertFalse(Hash::check('123456789', $user->password));

        $this->get('/management/operations/users')->assertOk();
        $this->getJson('/management/data/users')->assertOk();
    }

    public function test_first_password_login_redirects_to_setup_and_api_cannot_issue_token(): void
    {
        $user = $this->manager([
            'must_change_password' => true,
            'password' => '123456789',
        ]);

        $this->post('/management/login', [
            'login' => $user->mobile,
            'password' => '123456789',
        ])->assertRedirect('/account/change-initial-password');

        auth('web')->logout();

        $this->postJson('/api/v1/auth/password/login', [
            'login' => $user->mobile,
            'password' => '123456789',
            'device_name' => 'test-device',
        ])->assertForbidden()->assertJsonPath(
            'code', 'PASSWORD_CHANGE_REQUIRED'
        );
    }

    private function manager(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'mobile_verified_at' => now(),
            'is_active' => true,
            'is_blocked' => false,
        ], $attributes));

        $role = Role::query()->create([
            'name' => 'account-manager-'.uniqid(),
            'display_name' => 'Account Manager',
            'is_system' => false,
        ]);

        $permissions = collect([
            'reports.platform.view', 'users.view', 'users.create',
        ])->map(fn (string $name): int => Permission::query()
            ->firstOrCreate(['name' => $name], [
                'display_name' => $name,
                'module' => 'users',
            ])->getKey())->all();

        $role->permissions()->sync($permissions);

        UserRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'scope_type' => null,
            'scope_id' => null,
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);

        return $user;
    }
}
