<?php

namespace App\Services\Support;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketAssigned;
use App\Events\SupportTicketMessageAdded;
use App\Events\SupportTicketResolved;
use App\Models\Building;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\User;
use App\Services\Security\BuildingAccessService;
use App\Services\Security\UnitResidentAccessService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportTicketWorkflowService
{
    public function __construct(
        private readonly BuildingAccessService $buildingAccess,
        private readonly UnitResidentAccessService $residentAccess,
        private readonly PermissionChecker $permissions,
        private readonly SupportSlaService $sla
    ) {
    }

    public function create(User $actor, array $data): SupportTicket
    {
        return DB::transaction(function () use ($actor, $data): SupportTicket {
            [$buildingId, $unitId] = $this->resolveContext(
                $actor,
                $data['building_id'] ?? null,
                $data['unit_id'] ?? null,
                'support-tickets.create'
            );

            $priority = isset($data['priority'])
                ? SupportPriority::from((string) $data['priority'])
                : SupportPriority::Medium;

            $deadlines = $this->sla->deadlines(
                $data['support_category_id'] ?? null,
                $priority
            );

            $ticket = SupportTicket::query()->create([
                'user_id' => $actor->getKey(),
                'building_id' => $buildingId,
                'unit_id' => $unitId,
                'support_category_id' => $data['support_category_id'] ?? null,
                'ticket_number' => $this->ticketNumber(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $priority,
                'status' => SupportTicketStatus::Open,
                'assigned_to' => null,
                'response_due_at' => $deadlines['response_due_at'],
                'resolution_due_at' => $deadlines['resolution_due_at'],
            ]);

            return $ticket->refresh();
        }, 3);
    }

    public function updateDetails(
        SupportTicket $ticket,
        User $actor,
        array $data
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $actor, $data): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (in_array(
                $ticket->status,
                [SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Resolved/closed tickets must be reopened before editing.',
                ]);
            }

            if (array_key_exists('building_id', $data) || array_key_exists('unit_id', $data)) {
                [$buildingId, $unitId] = $this->resolveContext(
                    $actor,
                    $data['building_id'] ?? $ticket->building_id,
                    $data['unit_id'] ?? $ticket->unit_id,
                    'support-tickets.update'
                );

                $data['building_id'] = $buildingId;
                $data['unit_id'] = $unitId;
            }

            $priorityChanged = array_key_exists('priority', $data)
                && (string) $data['priority'] !== $ticket->priority->value;

            $categoryChanged = array_key_exists('support_category_id', $data)
                && (int) ($data['support_category_id'] ?? 0)
                    !== (int) ($ticket->support_category_id ?? 0);

            $ticket->update($data);

            if ($priorityChanged || $categoryChanged) {
                $deadlines = $this->sla->deadlines(
                    $ticket->support_category_id,
                    $ticket->priority
                );

                $ticket->forceFill([
                    'response_due_at' => $ticket->first_response_at
                        ? $ticket->response_due_at
                        : $deadlines['response_due_at'],
                    'resolution_due_at' => $deadlines['resolution_due_at'],
                ])->save();
            }

            return $ticket->refresh();
        }, 3);
    }

    public function assign(
        SupportTicket $ticket,
        User $assignee
    ): SupportTicket {
        if (! $assignee->is_active || $assignee->is_blocked) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Assignee must be an active, unblocked user.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $assignee): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (in_array(
                $ticket->status,
                [SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Resolved/closed tickets cannot be assigned.',
                ]);
            }

            $ticket->update([
                'assigned_to' => $assignee->getKey(),
                'assigned_at' => now(),
                'status' => SupportTicketStatus::Assigned,
            ]);

            $assigned = $ticket->refresh();

            DB::afterCommit(
                fn () => SupportTicketAssigned::dispatch($assigned)
            );

            return $assigned;
        }, 3);
    }

    public function addMessage(
        SupportTicket $ticket,
        User $actor,
        string $message,
        bool $internal = false
    ): SupportMessage {
        return DB::transaction(function () use ($ticket, $actor, $message, $internal): SupportMessage {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (in_array(
                $ticket->status,
                [SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Resolved/closed tickets must be reopened before adding messages.',
                ]);
            }

            $isRequester = (int) $ticket->user_id === (int) $actor->getKey();

            if ($internal && $isRequester) {
                throw ValidationException::withMessages([
                    'is_internal' => 'Ticket requester cannot create internal support notes.',
                ]);
            }

            $record = SupportMessage::query()->create([
                'support_ticket_id' => $ticket->getKey(),
                'user_id' => $actor->getKey(),
                'message' => $message,
                'is_internal' => $internal,
            ]);

            if (! $internal && ! $isRequester && $ticket->first_response_at === null) {
                $ticket->forceFill([
                    'first_response_at' => now(),
                ])->save();
            }

            if (! $internal) {
                if ($isRequester && $ticket->status === SupportTicketStatus::WaitingUser) {
                    $ticket->forceFill([
                        'status' => SupportTicketStatus::InProgress,
                    ])->save();
                } elseif (! $isRequester && in_array(
                    $ticket->status,
                    [SupportTicketStatus::Open, SupportTicketStatus::Assigned],
                    true
                )) {
                    $ticket->forceFill([
                        'status' => SupportTicketStatus::InProgress,
                    ])->save();
                }
            }

            $messageRecord = $record->refresh();

            DB::afterCommit(
                fn () => SupportTicketMessageAdded::dispatch($messageRecord)
            );

            return $messageRecord;
        }, 3);
    }

    public function start(SupportTicket $ticket): SupportTicket
    {
        return $this->transition(
            $ticket,
            [
                SupportTicketStatus::Open,
                SupportTicketStatus::Assigned,
                SupportTicketStatus::WaitingUser,
            ],
            SupportTicketStatus::InProgress
        );
    }

    public function waitForUser(SupportTicket $ticket): SupportTicket
    {
        return $this->transition(
            $ticket,
            [SupportTicketStatus::Assigned, SupportTicketStatus::InProgress],
            SupportTicketStatus::WaitingUser
        );
    }

    public function resolve(
        SupportTicket $ticket,
        string $resolution
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $resolution): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (! in_array(
                $ticket->status,
                [
                    SupportTicketStatus::Open,
                    SupportTicketStatus::Assigned,
                    SupportTicketStatus::InProgress,
                    SupportTicketStatus::WaitingUser,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Ticket cannot be resolved in its current status.',
                ]);
            }

            $ticket->update([
                'status' => SupportTicketStatus::Resolved,
                'resolution' => $resolution,
                'resolved_at' => now(),
                'closed_at' => null,
            ]);

            $resolved = $ticket->refresh();

            DB::afterCommit(
                fn () => SupportTicketResolved::dispatch($resolved)
            );

            return $resolved;
        }, 3);
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        return DB::transaction(function () use ($ticket): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if ($ticket->status === SupportTicketStatus::Closed) {
                return $ticket;
            }

            if ($ticket->status !== SupportTicketStatus::Resolved) {
                throw ValidationException::withMessages([
                    'status' => 'Only a resolved ticket can be closed.',
                ]);
            }

            $ticket->update([
                'status' => SupportTicketStatus::Closed,
                'closed_at' => now(),
            ]);

            return $ticket->refresh();
        }, 3);
    }

    public function reopen(SupportTicket $ticket): SupportTicket
    {
        return DB::transaction(function () use ($ticket): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (! in_array(
                $ticket->status,
                [SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Only resolved/closed tickets can be reopened.',
                ]);
            }

            $ticket->update([
                'status' => $ticket->assigned_to
                    ? SupportTicketStatus::Assigned
                    : SupportTicketStatus::Open,
                'resolution' => null,
                'resolved_at' => null,
                'closed_at' => null,
            ]);

            $this->sla->refreshResolutionDeadline($ticket);

            return $ticket->refresh();
        }, 3);
    }

    private function transition(
        SupportTicket $ticket,
        array $from,
        SupportTicketStatus $to
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $from, $to): SupportTicket {
            $ticket = SupportTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            if (! in_array($ticket->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => "Ticket cannot transition to {$to->value} from its current status.",
                ]);
            }

            $ticket->update([
                'status' => $to,
            ]);

            return $ticket->refresh();
        }, 3);
    }

    private function resolveContext(
        User $actor,
        ?int $buildingId,
        ?int $unitId,
        string $permission
    ): array {
        $unit = null;
        $building = null;

        if ($unitId !== null) {
            $unit = Unit::query()
                ->with('floor.block.building')
                ->findOrFail($unitId);

            $unitBuilding = $unit->floor?->block?->building;

            if (! $unitBuilding) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit does not resolve to a Building.',
                ]);
            }

            if ($buildingId !== null && (int) $unitBuilding->id !== (int) $buildingId) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit does not belong to the selected Building.',
                ]);
            }

            $building = $unitBuilding;
            $buildingId = $building->id;
        } elseif ($buildingId !== null) {
            $building = Building::query()->findOrFail($buildingId);
        }

        if ($building && ! $this->buildingAccess->allows($actor, $building)) {
            abort(403);
        }

        if (
            $building
            && $unit
            && ! $this->permissions->allows(
                $actor,
                $permission,
                $building
            )
            && ! $this->residentAccess->allows(
                $actor,
                $unit
            )
        ) {
            abort(403);
        }

        return [$buildingId, $unit?->id];
    }

    private function ticketNumber(): string
    {
        do {
            $number = 'SUP-'
                .now()->format('Ymd')
                .'-'
                .strtoupper(Str::random(10));
        } while (
            SupportTicket::query()
                ->where('ticket_number', $number)
                ->exists()
        );

        return $number;
    }
}
