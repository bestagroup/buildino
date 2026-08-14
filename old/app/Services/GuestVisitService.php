<?php

namespace App\Services;

use App\Enums\GuestAccessAction;
use App\Enums\GuestVisitStatus;
use App\Models\GuestAccessLog;
use App\Models\GuestVisit;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuestVisitService
{
    public function __construct(
        private readonly GuestDirectoryService $guests
    ) {
    }

    public function register(
        Unit $unit,
        User $actor,
        array $data
    ): GuestVisit {
        return DB::transaction(function () use (
            $unit,
            $actor,
            $data
        ): GuestVisit {
            $guest = $this->guests->resolveForUnit(
                $unit,
                $data['guest']
            );

            return GuestVisit::query()->create([
                'guest_id' => $guest->getKey(),
                'unit_id' => $unit->getKey(),
                'registered_by' => $actor->getKey(),

                'expected_entry_at' => $data['expected_entry_at']
                    ?? null,

                'expected_exit_at' => $data['expected_exit_at']
                    ?? null,

                'status' => GuestVisitStatus::Invited,
                'description' => $data['description']
                    ?? null,
            ]);
        });
    }

    public function update(
        GuestVisit $visit,
        array $data
    ): GuestVisit {
        if (
            $visit->status !== GuestVisitStatus::Invited
        ) {
            throw ValidationException::withMessages([
                'visit' => [
                    'Only an invited visit can be edited.',
                ],
            ]);
        }

        $visit->update($data);

        return $visit->refresh();
    }

    public function cancel(
        GuestVisit $visit
    ): GuestVisit {
        return DB::transaction(function () use (
            $visit
        ): GuestVisit {
            $visit = GuestVisit::query()
                ->lockForUpdate()
                ->findOrFail(
                    $visit->getKey()
                );

            if (
                $visit->status === GuestVisitStatus::Cancelled
            ) {
                return $visit;
            }

            if (
                $visit->status !== GuestVisitStatus::Invited
            ) {
                throw ValidationException::withMessages([
                    'visit' => [
                        'Only an invited visit can be cancelled.',
                    ],
                ]);
            }

            $visit->update([
                'status' => GuestVisitStatus::Cancelled,
            ]);

            return $visit->refresh();
        });
    }

    public function recordEntry(
        GuestVisit $visit,
        User $actor,
        array $data
    ): GuestAccessLog {
        $visit->refresh();

        /*
         * Expiration is persisted before opening the transaction so
         * the state change is not rolled back by the validation error.
         */
        if (
            $visit->status === GuestVisitStatus::Invited
            && $visit->expected_exit_at
            && $visit->expected_exit_at->isPast()
        ) {
            $visit->update([
                'status' => GuestVisitStatus::Expired,
            ]);

            throw ValidationException::withMessages([
                'visit' => [
                    'This guest visit has expired.',
                ],
            ]);
        }

        return DB::transaction(function () use (
            $visit,
            $actor,
            $data
        ): GuestAccessLog {
            $visit = GuestVisit::query()
                ->with('guest')
                ->lockForUpdate()
                ->findOrFail(
                    $visit->getKey()
                );

            if (
                $visit->status !== GuestVisitStatus::Invited
            ) {
                throw ValidationException::withMessages([
                    'visit' => [
                        'Guest entry is allowed only for an invited visit.',
                    ],
                ]);
            }

            $log = $this->createAccessLog(
                $visit,
                $actor,
                GuestAccessAction::Entry,
                $data
            );

            $visit->update([
                'status' => GuestVisitStatus::Entered,
            ]);

            return $log;
        });
    }

    public function recordExit(
        GuestVisit $visit,
        User $actor,
        array $data
    ): GuestAccessLog {
        return DB::transaction(function () use (
            $visit,
            $actor,
            $data
        ): GuestAccessLog {
            $visit = GuestVisit::query()
                ->with('guest')
                ->lockForUpdate()
                ->findOrFail(
                    $visit->getKey()
                );

            if (
                $visit->status !== GuestVisitStatus::Entered
            ) {
                throw ValidationException::withMessages([
                    'visit' => [
                        'Guest exit is allowed only after an entry has been recorded.',
                    ],
                ]);
            }

            $occurredAt = $this->occurredAt(
                $data
            );

            $lastEntry = $visit->guestAccessLogs()
                ->where(
                    'action',
                    GuestAccessAction::Entry->value
                )
                ->latest('occurred_at')
                ->first();

            if (
                $lastEntry
                && $occurredAt->lt(
                    $lastEntry->occurred_at
                )
            ) {
                throw ValidationException::withMessages([
                    'occurred_at' => [
                        'Exit time cannot be before entry time.',
                    ],
                ]);
            }

            $data['occurred_at'] = $occurredAt;

            $log = $this->createAccessLog(
                $visit,
                $actor,
                GuestAccessAction::Exit,
                $data
            );

            $visit->update([
                'status' => GuestVisitStatus::Exited,
            ]);

            return $log;
        });
    }

    private function createAccessLog(
        GuestVisit $visit,
        User $actor,
        GuestAccessAction $action,
        array $data
    ): GuestAccessLog {
        return GuestAccessLog::query()->create([
            'guest_visit_id' => $visit->getKey(),
            'action' => $action,
            'occurred_at' => $this->occurredAt(
                $data
            ),
            'gate' => $data['gate'] ?? null,
            'entry_method' => $data['entry_method'] ?? null,
            'verified_by' => $actor->getKey(),

            'vehicle_plate' => $data['vehicle_plate']
                ?? $visit->guest?->vehicle_plate,

            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function occurredAt(
        array $data
    ): CarbonImmutable {
        return isset($data['occurred_at'])
            ? CarbonImmutable::parse(
                $data['occurred_at']
            )
            : CarbonImmutable::now();
    }
}
