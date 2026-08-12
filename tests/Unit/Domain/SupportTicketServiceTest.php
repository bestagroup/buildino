<?php

namespace Tests\Unit\Domain;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class SupportTicketServiceTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_ticket_can_be_assigned_and_resolved(): void
    {
        $graph = $this->createBuildingGraph();
        $requester = $this->createUser();
        $assignee = $this->createUser();

        $ticket = SupportTicket::query()->create([
            'user_id' => $requester->id,
            'building_id' => $graph['building']->id,
            'unit_id' => $graph['unit']->id,
            'ticket_number' => 'T-001',
            'subject' => 'Elevator',
            'description' => 'Problem',
            'priority' => 'medium',
            'status' => SupportTicketStatus::Open,
        ]);

        $service = app(SupportTicketService::class);
        $assigned = $service->assign($ticket, $assignee);

        $this->assertSame(SupportTicketStatus::Assigned, $assigned->status);
        $this->assertSame($assignee->id, $assigned->assigned_to);

        $resolved = $service->resolve($assigned, 'Fixed');

        $this->assertSame(SupportTicketStatus::Resolved, $resolved->status);
        $this->assertSame('Fixed', $resolved->resolution);
        $this->assertNotNull($resolved->resolved_at);
    }
}
