<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuildingBankAccountRequest;
use App\Http\Resources\V1\BuildingBankAccountResource;
use App\Models\Building;
use App\Models\BuildingBankAccount;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BuildingBankAccountController extends Controller
{
    public function index(
        Request $request,
        Building $building,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bank-accounts.view',
                $building
            ),
            403
        );

        return BuildingBankAccountResource::collection(
            BuildingBankAccount::query()
                ->where('building_id', $building->getKey())
                ->latest('id')
                ->paginate(20)
        );
    }

    public function store(
        StoreBuildingBankAccountRequest $request,
        Building $building,
        PermissionChecker $permissions
    ) {
        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bank-accounts.create',
                $building
            ),
            403
        );

        $data = $request->validated();

        $account = DB::transaction(function () use ($building, $data): BuildingBankAccount {
            if (($data['is_default'] ?? false) === true) {
                BuildingBankAccount::query()
                    ->where('building_id', $building->getKey())
                    ->update(['is_default' => false]);
            }

            return BuildingBankAccount::query()->create([
                ...$data,
                'building_id' => $building->getKey(),
                'is_verified' => false,
                'is_active' => true,
            ]);
        });

        return (new BuildingBankAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function verify(
        Request $request,
        BuildingBankAccount $buildingBankAccount,
        PermissionChecker $permissions
    ): BuildingBankAccountResource {
        $buildingBankAccount->loadMissing('building');

        abort_unless(
            $permissions->allows(
                $request->user(),
                'building-bank-accounts.verify',
                $buildingBankAccount->building
            ),
            403
        );

        $buildingBankAccount->update([
            'is_verified' => true,
            'verified_by' => $request->user()->getKey(),
            'verified_at' => now(),
        ]);

        return new BuildingBankAccountResource(
            $buildingBankAccount->refresh()
        );
    }
}
