<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingComplexRequest;
use App\Models\Floor;
use App\Models\Unit;
use App\Services\Web\BuildingComplexConnectionService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class BuildingSetupActionsController extends Controller
{
    public function complete(Request $request, Building $building, PermissionChecker $permissions): JsonResponse
    {
        foreach (['blocks.create', 'floors.create', 'units.create'] as $permission) {
            abort_unless($permissions->allows($request->user(), $permission, $building), 403);
        }
        DB::transaction(function () use ($building): void {
            $building = Building::query()->lockForUpdate()->findOrFail($building->id);
            $floors = Floor::query()->whereHas('block', fn ($q) => $q->where('building_id', $building->id))->count();
            $units = Unit::query()->whereHas('floor.block', fn ($q) => $q->where('building_id', $building->id))->count();
            abort_unless($floors > 0 && $units > 0, 422, 'ابتدا طبقات و حداقل یک واحد را ثبت کنید.');
            $building->forceFill(['floors_count' => $floors, 'units_count' => $units, 'setup_completed_at' => now()])->save();
        });
        return response()->json(['completed' => true]);
    }

    public function connect(Request $request, Building $building, BuildingComplexConnectionService $connections): RedirectResponse
    {
        $data = $request->validate(['complex_code' => ['required', 'string', 'max:100']]);
        $connections->request($request->user(), $building, $data['complex_code']);
        return back()->with('status', 'درخواست اتصال ارسال شد؛ تا تأیید مدیر مجتمع، دسترسی شما فقط به بلوک خودتان است.');
    }

    public function review(Request $request, BuildingComplexRequest $connection, BuildingComplexConnectionService $connections): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected,cancelled']]);
        $connections->review($request->user(), $connection, $data['decision']);
        return back()->with('status', 'وضعیت درخواست به‌روزرسانی شد.');
    }
}
