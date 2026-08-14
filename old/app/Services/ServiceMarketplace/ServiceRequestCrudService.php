<?php

namespace App\Services\ServiceMarketplace;

use App\Enums\ServiceRequestStatus;
use App\Models\Building;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWalletPayment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Security\BuildingAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ServiceRequestCrudService
{
    public function __construct(
        private readonly BuildingAccessService $buildingAccess
    ) {
    }

    public function create(User $actor, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($actor, $data): ServiceRequest {
            $building = Building::query()->findOrFail(
                (int) $data['building_id']
            );

            if (! $this->buildingAccess->allows($actor, $building)) {
                abort(403);
            }

            $this->assertUnitBelongsToBuilding(
                $data['unit_id'] ?? null,
                $building
            );

            return ServiceRequest::query()->create([
                'request_number' => $this->requestNumber(),
                'building_id' => $building->getKey(),
                'unit_id' => $data['unit_id'] ?? null,
                'requested_by' => $actor->getKey(),
                'type' => $data['type'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => ServiceRequestStatus::Open,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'assigned_to' => null,
                'assigned_at' => null,
            ])->refresh();
        }, 3);
    }

    public function update(
        ServiceRequest $request,
        User $actor,
        array $data
    ): ServiceRequest {
        return DB::transaction(function () use ($request, $actor, $data): ServiceRequest {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if (in_array(
                $request->status,
                [
                    ServiceRequestStatus::InProgress,
                    ServiceRequestStatus::AwaitingConfirmation,
                    ServiceRequestStatus::Completed,
                    ServiceRequestStatus::Cancelled,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Service request cannot be edited in its current lifecycle state.',
                ]);
            }

            if (array_key_exists('building_id', $data)) {
                $building = Building::query()->findOrFail(
                    (int) $data['building_id']
                );

                if (! $this->buildingAccess->allows($actor, $building)) {
                    abort(403);
                }
            } else {
                $request->loadMissing('building');
                $building = $request->building;
            }

            $this->assertUnitBelongsToBuilding(
                $data['unit_id'] ?? $request->unit_id,
                $building
            );

            $request->update($data);

            return $request->refresh();
        }, 3);
    }

    public function assign(
        ServiceRequest $request,
        User $provider
    ): ServiceRequest {
        if (! $provider->is_active || $provider->is_blocked) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Service provider must be active and unblocked.',
            ]);
        }

        return DB::transaction(function () use ($request, $provider): ServiceRequest {
            $request = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->getKey());

            if (in_array(
                $request->status,
                [
                    ServiceRequestStatus::InProgress,
                    ServiceRequestStatus::AwaitingConfirmation,
                    ServiceRequestStatus::Completed,
                    ServiceRequestStatus::Cancelled,
                ],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => 'Service provider cannot be changed in the current lifecycle state.',
                ]);
            }

            $activePayment = ServiceRequestWalletPayment::query()
                ->where('service_request_id', $request->getKey())
                ->whereIn('status', ['locked', 'settled'])
                ->exists();

            if ($activePayment) {
                throw ValidationException::withMessages([
                    'payment' => 'Provider cannot be changed after wallet payment has been locked.',
                ]);
            }

            $request->update([
                'assigned_to' => $provider->getKey(),
                'assigned_at' => now(),
                'status' => ServiceRequestStatus::Assigned,
            ]);

            return $request->refresh();
        }, 3);
    }

    private function assertUnitBelongsToBuilding(
        ?int $unitId,
        Building $building
    ): void {
        if ($unitId === null) {
            return;
        }

        $belongs = Unit::query()
            ->whereKey($unitId)
            ->whereHas(
                'floor.block',
                fn ($query) => $query->where(
                    'building_id',
                    $building->getKey()
                )
            )
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit does not belong to the selected Building.',
            ]);
        }
    }

    private function requestNumber(): string
    {
        do {
            $number = 'SR-'
                .now()->format('Ymd')
                .'-'
                .strtoupper(Str::random(10));
        } while (
            ServiceRequest::query()
                ->where('request_number', $number)
                ->exists()
        );

        return $number;
    }
}
