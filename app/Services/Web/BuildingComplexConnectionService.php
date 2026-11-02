<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\BuildingComplexRequest;
use App\Models\Complex;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BuildingComplexConnectionService
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function request(User $actor, Building $building, string $code): void
    {
        abort_unless($this->permissions->allows($actor, 'buildings.update', $building), 403);
        DB::transaction(function () use ($actor, $building, $code): void {
            $building = Building::query()->lockForUpdate()->findOrFail($building->id);
            abort_unless($building->structure_type === 'complex_block' && $building->complex?->workspace_type === 'unlinked', 422);
            $target = Complex::query()->where('code', trim($code))->where('is_active', true)
                ->where(fn ($query) => $query->where('workspace_type', 'complex')->orWhereNull('workspace_type'))->first();
            if (!$target) {
                throw ValidationException::withMessages(['complex_code' => 'کد مجتمع معتبر نیست؛ کد را از مدیر مجتمع دریافت کنید.']);
            }
            if (BuildingComplexRequest::query()->where('building_id', $building->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['complex_code' => 'یک درخواست در انتظار بررسی دارید؛ ابتدا آن را لغو کنید.']);
            }
            BuildingComplexRequest::query()->create([
                'building_id' => $building->id, 'complex_id' => $target->id, 'requested_by' => $actor->id,
            ]);
        });
    }

    public function review(User $actor, BuildingComplexRequest $connection, string $decision): void
    {
        DB::transaction(function () use ($actor, $connection, $decision): void {
            // Lock in the same order as request/cancellation to serialize retries.
            $building = Building::query()->lockForUpdate()->findOrFail($connection->building_id);
            $connection = BuildingComplexRequest::query()->lockForUpdate()->findOrFail($connection->id);
            $complex = Complex::query()->findOrFail($connection->complex_id);
            if ($decision === 'cancelled') {
                abort_unless($this->permissions->allows($actor, 'buildings.update', $building), 403);
            } else {
                abort_unless($this->permissions->allows($actor, 'buildings.create', $complex)
                    && $this->permissions->allows($actor, 'complexes.update', $complex), 403);
            }
            abort_unless($connection->status === 'pending', 409);
            if ($decision === 'approved') {
                $requester = User::query()->findOrFail($connection->requested_by);
                abort_unless($this->permissions->allows($requester, 'buildings.update', $building), 409);
                abort_unless($complex->is_active && in_array($complex->workspace_type, [null, 'complex'], true)
                    && $building->structure_type === 'complex_block' && $building->complex?->workspace_type === 'unlinked', 409);
                // Only the parent changes. The manager's Building-scoped assignment stays intact.
                $building->update(['complex_id' => $complex->id]);
            }
            $connection->update(['status' => $decision, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
    }
}
