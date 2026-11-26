<?php

namespace App\Services\Web;

use App\Models\Building;
use App\Models\BuildingStaff;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BuildingStaffService
{
    public function options(User $actor, Building $building): array
    {
        $checker = app(PermissionChecker::class);

        return array_filter(config('building_staff.permissions'), fn ($name) => $checker->allows($actor, $name, $building), ARRAY_FILTER_USE_KEY);
    }

    public function save(User $actor, Building $building, array $data, ?BuildingStaff $staff = null): BuildingStaff
    {
        abort_unless(app(PermissionChecker::class)->allows($actor, 'building-staff.manage', $building), 403);
        abort_if($staff && (int) $staff->building_id !== (int) $building->id, 404);
        $names = $data['permissions'] ?? [];
        if (array_diff($names, array_keys($this->options($actor, $building)))) {
            throw ValidationException::withMessages(['permissions' => 'مجوز انتخاب‌شده قابل واگذاری نیست.']);
        }

        return DB::transaction(function () use ($actor, $building, $data, $staff, $names) {
            Building::whereKey($building->id)->lockForUpdate()->firstOrFail();
            if ($staff) {
                $staff = BuildingStaff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
                $assignment = $staff->assignment()->lockForUpdate()->firstOrFail();
                $role = $assignment->role;
                abort_unless($assignment->scope_type === $building->getMorphClass() && (int) $assignment->scope_id === (int) $building->id, 409);
            } else {
                $user = User::withTrashed()->where('mobile', $data['mobile'])->lockForUpdate()->first();
                if ($user && ($user->trashed() || ! $user->is_active || $user->is_blocked)) {
                    throw ValidationException::withMessages(['mobile' => 'این حساب قابل اتصال نیست؛ با پشتیبانی سامانه تماس بگیرید.']);
                }
                $user ??= User::create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'mobile' => $data['mobile'], 'password' => Str::random(64), 'is_active' => true, 'is_blocked' => false]);
                if (BuildingStaff::where('building_id', $building->id)->where('user_id', $user->id)->exists()) {
                    throw ValidationException::withMessages(['mobile' => 'این کارمند قبلاً ثبت شده است؛ از ویرایش استفاده کنید.']);
                }
                $role = Role::create(['name' => 'building_staff_'.Str::uuid(), 'display_name' => 'کارمند ساختمان', 'is_system' => false]);
                $assignment = UserRoleAssignment::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => $building->getMorphClass(), 'scope_id' => $building->id, 'is_active' => true, 'assigned_by' => $actor->id]);
                $staff = new BuildingStaff(['building_id' => $building->id, 'user_id' => $user->id, 'assignment_id' => $assignment->id, 'created_by' => $actor->id]);
            }
            // Each employment has an independent role; never modify the user's other roles.
            $permissionIds = Permission::whereIn('name', $names)->pluck('id');
            if ($permissionIds->count() !== count(array_unique($names))) {
                throw ValidationException::withMessages(['permissions' => 'یک مجوز در سامانه تعریف نشده است.']);
            }
            $role->permissions()->sync($permissionIds);
            $assignment->update(['is_active' => (bool) $data['is_active']]);
            $staff->fill(['employee_type' => $data['employee_type'], 'services' => $data['services']]);
            $staff->save();

            return $staff;
        });
    }
}
