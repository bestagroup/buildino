<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_read_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['api']);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }
}
