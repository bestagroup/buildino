<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

final class SupportTicketPageWebTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_ticket_page_has_scoped_context_and_preserves_inactive_categories_used_by_visible_tickets(): void
    {
        $inside = $this->createBuildingGraph(); $outside = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, $inside['building'], ['support-tickets.view', 'support-tickets.create', 'support-tickets.update']);
        $active = SupportCategory::query()->create(['title' => 'عمومی', 'is_active' => true]);
        $old = SupportCategory::query()->create(['title' => 'قدیمی', 'is_active' => false]);
        SupportCategory::query()->create(['title' => 'غیرفعال دیگر', 'is_active' => false]);
        $ticket = $this->ticket($user, $inside, ['support_category_id' => $old->id]);
        $this->actingAs($user)->get('/management/operations/support-tickets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('management/support/Tickets')->has('workspace.buildings', 1)->has('workspace.units', 1)->has('workspace.categories', 2)
            ->where('workspace.buildings.0.id', $inside['building']->id)
            ->where('workspace.buildings.0.abilities.update', true)->where('workspace.globalAbilities.update', false)
            ->where('workspace.canManageCategories', false)->where('workspace.canCreate', true));
    }

    public function test_internal_message_history_follows_the_existing_backend_permission(): void
    {
        $graph = $this->createBuildingGraph(); $requester = $this->createUser(); $agent = $this->createUser();
        $this->grant($requester, $graph['building'], ['support-tickets.view']);
        $this->grant($agent, $graph['building'], ['support-tickets.view', 'support-tickets.update']);
        $ticket = $this->ticket($requester, $graph, ['assigned_to' => $agent->id]);
        foreach ([false, true] as $internal) SupportMessage::query()->create(['support_ticket_id' => $ticket->id, 'user_id' => $agent->id, 'message' => $internal ? 'یادداشت داخلی' : 'پاسخ عمومی', 'is_internal' => $internal]);
        $this->actingAs($requester)->getJson('/api/v1/support-tickets/'.$ticket->id.'/messages')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_internal', false);
        $this->actingAs($agent)->getJson('/api/v1/support-tickets/'.$ticket->id.'/messages')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/support-tickets/'.$ticket->id)->assertOk()->assertJsonPath('data.assigned_to.id', $agent->id)->assertJsonCount(1, 'data.support_messages');
    }

    public function test_general_ticket_owner_context_does_not_leak_buildings_or_privileged_actions(): void
    {
        $inside = $this->createBuildingGraph(); $outside = $this->createBuildingGraph(); $user = $this->createUser();
        $this->grant($user, $inside['building'], ['support-tickets.view']);
        $this->ticket($user, $outside);
        $this->actingAs($user)->get('/management/operations/support-tickets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('management/support/Tickets')->has('workspace.buildings', 2)
            ->where('workspace.globalAbilities.update', false)->where('workspace.globalAbilities.delete', false)
            ->where('workspace.buildings', fn ($items) => collect($items)->every(fn ($item) => ! $item['abilities']['update'])));
    }

    private function ticket($user, array $graph, array $attributes = []): SupportTicket
    {
        return SupportTicket::query()->create(array_merge(['ticket_number' => 'SUP-'.uniqid(), 'user_id' => $user->id, 'building_id' => $graph['building']->id, 'unit_id' => $graph['unit']->id, 'subject' => 'پیگیری', 'description' => 'شرح درخواست', 'priority' => 'medium', 'status' => 'open'], $attributes));
    }

    private function grant($user, $scope, array $names): void
    {
        $role = Role::query()->create(['name' => 'support-ui-'.uniqid(), 'display_name' => 'پشتیبانی', 'is_system' => false]);
        $role->permissions()->sync(collect(['reports.dashboard.view', ...$names])->map(fn ($name) => Permission::query()->firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => explode('.', $name)[0]])->id));
        UserRoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $scope->getMorphClass(), 'scope_id' => $scope->id, 'is_active' => true]);
    }
}
