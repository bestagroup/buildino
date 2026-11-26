<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingStaff;
use App\Services\Security\BuildingPermissionScopeService;
use App\Services\Web\BuildingStaffService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BuildingStaffController extends Controller
{
    public function index(Request $request, BuildingStaffService $service)
    {
        $request->validate(['building_id' => ['nullable', 'integer', 'min:1'], 'edit' => ['nullable', 'integer', 'min:1']]);
        $ids = app(BuildingPermissionScopeService::class)->buildingIds($request->user(), 'building-staff.manage');
        $buildings = Building::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('title')->get()
            ->filter(fn ($b) => app(PermissionChecker::class)->allows($request->user(), 'building-staff.manage', $b))->values();
        $building = $request->filled('building_id') ? $buildings->firstWhere('id', $request->integer('building_id')) : ($buildings->count() === 1 ? $buildings->first() : null);
        abort_if($request->filled('building_id') && ! $building, 403);
        $staff = $building ? BuildingStaff::where('building_id', $building->id)->with(['user', 'assignment.role.permissions'])->latest()->paginate(25)->withQueryString() : null;
        $editing = $request->filled('edit') && $building ? BuildingStaff::where('building_id', $building->id)->with(['user', 'assignment.role.permissions'])->findOrFail($request->integer('edit')) : null;
        $options = $building ? $service->options($request->user(), $building) : [];

        return view('management.building-staff', compact('buildings', 'building', 'staff', 'editing', 'options'));
    }

    public function store(Request $request, Building $building, BuildingStaffService $service)
    {
        return $this->save($request, $building, $service);
    }

    public function update(Request $request, Building $building, BuildingStaff $staff, BuildingStaffService $service)
    {
        return $this->save($request, $building, $service, $staff);
    }

    private function save(Request $request, Building $building, BuildingStaffService $service, ?BuildingStaff $staff = null)
    {
        abort_unless(app(PermissionChecker::class)->allows($request->user(), 'building-staff.manage', $building), 403);
        abort_if($staff && (int) $staff->building_id !== (int) $building->id, 404);
        $types = config('building_staff.types');
        $type = $request->input('employee_type');
        $serviceKeys = is_string($type) ? array_keys($types[$type]['services'] ?? []) : [];
        $data = $request->validate([
            'first_name' => [$staff ? 'nullable' : 'required', 'string', 'max:100'], 'last_name' => [$staff ? 'nullable' : 'required', 'string', 'max:100'],
            'mobile' => [$staff ? 'nullable' : 'required', 'regex:/^09[0-9]{9}$/'],
            'employee_type' => ['required', Rule::in(array_keys($types))],
            'services' => ['required', 'array', 'min:1'], 'services.*' => ['required', 'string', 'distinct', Rule::in($serviceKeys)],
            'permissions' => ['nullable', 'array'], 'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys($service->options($request->user(), $building)))],
            'is_active' => ['required', 'boolean'],
        ]);
        $service->save($request->user(), $building, $data, $staff);

        return redirect()->route('management.operations.show',['resource' => 'building-staff', 'building_id' => $building->id])->with('status','کارمند و دسترسی‌های اختصاصی ساختمان ذخیره شد.');
    }
}
