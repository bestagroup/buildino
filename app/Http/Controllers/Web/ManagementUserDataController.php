<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ManagementUserDataController extends Controller
{
    public function __construct(
        private readonly PermissionChecker $permissions
    ) {
    }

    public function users(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.view'
        );

        $query = User::query()
            ->with([
                'userRoleAssignments.role:id,name,display_name',
            ])
            ->latest('id');

        if (
            $search = trim(
                (string) $request->query('search')
            )
        ) {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where(
                        'first_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'last_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'mobile',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'national_code',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        $users = $query
            ->paginate(
                min(
                    max(
                        $request->integer(
                            'per_page',
                            25
                        ),
                        1
                    ),
                    100
                )
            );

        return response()->json(
            $users
        );
    }

    public function storeUser(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.create'
        );

        $data = $request->validate([
            'first_name' =>
                ['required', 'string', 'max:255'],
            'last_name' =>
                ['required', 'string', 'max:255'],
            'national_code' => [
                'nullable',
                'string',
                'max:20',
                'unique:users,national_code',
            ],
            'mobile' => [
                'required',
                'string',
                'max:20',
                'unique:users,mobile',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
            ],
            'is_active' =>
                ['sometimes', 'boolean'],
            'is_blocked' =>
                ['sometimes', 'boolean'],
            'verify_mobile' =>
                ['sometimes', 'boolean'],
            'verify_email' =>
                ['sometimes', 'boolean'],
        ]);

        $user = DB::transaction(
            function () use ($data): User {
                return User::query()->create([
                    'first_name' =>
                        $data['first_name'],
                    'last_name' =>
                        $data['last_name'],
                    'national_code' =>
                        $data['national_code']
                        ?? null,
                    'mobile' =>
                        $data['mobile'],
                    'email' =>
                        $data['email']
                        ?? null,
                    'password' =>
                        Hash::make(
                            $data['password']
                        ),
                    'mobile_verified_at' =>
                        ($data[
                            'verify_mobile'
                        ] ?? true)
                            ? now()
                            : null,
                    'email_verified_at' =>
                        (
                            ! empty(
                                $data['email']
                            )
                            && (
                                $data[
                                    'verify_email'
                                ] ?? false
                            )
                        )
                            ? now()
                            : null,
                    'is_active' =>
                        $data['is_active']
                        ?? true,
                    'is_blocked' =>
                        $data['is_blocked']
                        ?? false,
                ]);
            }
        );

        return response()->json([
            'data' => $this->userPayload(
                $user
            ),
        ], 201);
    }

    public function updateUser(
        Request $request,
        User $user
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.update'
        );

        $data = $request->validate([
            'first_name' =>
                ['sometimes', 'string', 'max:255'],
            'last_name' =>
                ['sometimes', 'string', 'max:255'],
            'national_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique(
                    'users',
                    'national_code'
                )->ignore(
                    $user->getKey()
                ),
            ],
            'mobile' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique(
                    'users',
                    'mobile'
                )->ignore(
                    $user->getKey()
                ),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique(
                    'users',
                    'email'
                )->ignore(
                    $user->getKey()
                ),
            ],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'max:255',
            ],
            'is_active' =>
                ['sometimes', 'boolean'],
            'is_blocked' =>
                ['sometimes', 'boolean'],
            'verify_mobile' =>
                ['sometimes', 'boolean'],
            'verify_email' =>
                ['sometimes', 'boolean'],
        ]);

        DB::transaction(
            function () use (
                $user,
                $data
            ): void {
                foreach ([
                    'first_name',
                    'last_name',
                    'national_code',
                    'mobile',
                    'email',
                    'is_active',
                    'is_blocked',
                ] as $field) {
                    if (
                        array_key_exists(
                            $field,
                            $data
                        )
                    ) {
                        $user->{$field} =
                            $data[$field];
                    }
                }

                if (
                    ! empty(
                        $data['password']
                    )
                ) {
                    $user->password =
                        Hash::make(
                            $data['password']
                        );
                }

                if (
                    array_key_exists(
                        'verify_mobile',
                        $data
                    )
                ) {
                    $user
                        ->mobile_verified_at =
                        $data['verify_mobile']
                            ? (
                                $user
                                    ->mobile_verified_at
                                ?? now()
                            )
                            : null;
                }

                if (
                    array_key_exists(
                        'verify_email',
                        $data
                    )
                ) {
                    $user
                        ->email_verified_at =
                        $data['verify_email']
                            ? (
                                $user
                                    ->email_verified_at
                                ?? now()
                            )
                            : null;
                }

                $user->save();
            }
        );

        return response()->json([
            'data' => $this->userPayload(
                $user->refresh()
            ),
        ]);
    }

    public function destroyUser(
        Request $request,
        User $user
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.delete'
        );

        abort_if(
            $request->user()->is(
                $user
            ),
            422,
            'حذف حساب کاربری جاری مجاز نیست.'
        );

        $user->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function roles(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.view'
        );

        $roles = Role::query()
            ->with([
                'permissions:id,name,module',
            ])
            ->withCount(
                'userRoleAssignments'
            )
            ->orderByDesc(
                'is_system'
            )
            ->orderBy(
                'display_name'
            )
            ->get()
            ->map(
                fn (Role $role): array =>
                    $this->rolePayload(
                        $role
                    )
            );

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function storeRole(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.create'
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:roles,name',
            ],
            'display_name' =>
                ['required', 'string', 'max:255'],
            'description' =>
                ['nullable', 'string', 'max:5000'],
            'is_system' =>
                ['sometimes', 'boolean'],
            'permission_ids' =>
                ['nullable', 'array'],
            'permission_ids.*' =>
                ['integer', 'exists:permissions,id'],
        ]);

        $role = DB::transaction(
            function () use ($data): Role {
                $role = Role::query()->create([
                    'name' =>
                        $data['name'],
                    'display_name' =>
                        $data['display_name'],
                    'description' =>
                        $data['description']
                        ?? null,
                    'is_system' =>
                        $data['is_system']
                        ?? false,
                ]);

                $role
                    ->permissions()
                    ->sync(
                        $data[
                            'permission_ids'
                        ] ?? []
                    );

                return $role;
            }
        );

        return response()->json([
            'data' =>
                $this->rolePayload(
                    $role->load(
                        'permissions:id,name,module'
                    )
                ),
        ], 201);
    }

    public function updateRole(
        Request $request,
        Role $role
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.update'
        );

        $data = $request->validate([
            'display_name' =>
                ['sometimes', 'string', 'max:255'],
            'description' =>
                ['sometimes', 'nullable', 'string', 'max:5000'],
            'permission_ids' =>
                ['sometimes', 'array'],
            'permission_ids.*' =>
                ['integer', 'exists:permissions,id'],
        ]);

        DB::transaction(
            function () use (
                $role,
                $data
            ): void {
                $role->fill(
                    collect(
                        $data
                    )
                        ->only([
                            'display_name',
                            'description',
                        ])
                        ->all()
                )->save();

                if (
                    array_key_exists(
                        'permission_ids',
                        $data
                    )
                ) {
                    $role
                        ->permissions()
                        ->sync(
                            $data[
                                'permission_ids'
                            ]
                        );
                }
            }
        );

        return response()->json([
            'data' =>
                $this->rolePayload(
                    $role
                        ->refresh()
                        ->load(
                            'permissions:id,name,module'
                        )
                ),
        ]);
    }

    public function destroyRole(
        Request $request,
        Role $role
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.delete'
        );

        abort_if(
            $role->is_system,
            422,
            'حذف Role سیستمی مجاز نیست.'
        );

        DB::transaction(
            function () use ($role): void {
                $role
                    ->permissions()
                    ->detach();

                $role->delete();
            }
        );

        return response()->json([
            'success' => true,
        ]);
    }

    public function assignments(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.view'
        );

        $query =
            UserRoleAssignment::query()
                ->with([
                    'user:id,first_name,last_name,mobile,email',
                    'role:id,name,display_name,is_system',
                ])
                ->latest('id');

        if (
            $request->filled(
                'user_id'
            )
        ) {
            $query->where(
                'user_id',
                $request->integer(
                    'user_id'
                )
            );
        }

        return response()->json([
            'data' =>
                $query
                    ->limit(100)
                    ->get()
                    ->map(
                        fn (
                            UserRoleAssignment $assignment
                        ): array =>
                            $this->assignmentPayload(
                                $assignment
                            )
                    ),
        ]);
    }

    public function storeAssignment(
        Request $request
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.update'
        );

        $data = $this->validateAssignment(
            $request
        );

        $assignment =
            UserRoleAssignment::query()
                ->create(
                    $this->assignmentData(
                        $request,
                        $data
                    )
                );

        return response()->json([
            'data' =>
                $this->assignmentPayload(
                    $assignment->load([
                        'user:id,first_name,last_name,mobile,email',
                        'role:id,name,display_name,is_system',
                    ])
                ),
        ], 201);
    }

    public function updateAssignment(
        Request $request,
        UserRoleAssignment $assignment
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.update'
        );

        $data = $this->validateAssignment(
            $request,
            true
        );

        $assignment->update(
            $this->assignmentData(
                $request,
                $data,
                $assignment
            )
        );

        return response()->json([
            'data' =>
                $this->assignmentPayload(
                    $assignment
                        ->refresh()
                        ->load([
                            'user:id,first_name,last_name,mobile,email',
                            'role:id,name,display_name,is_system',
                        ])
                ),
        ]);
    }

    public function destroyAssignment(
        Request $request,
        UserRoleAssignment $assignment
    ): JsonResponse {
        $this->requirePermission(
            $request,
            'users.update'
        );

        abort_if(
            $assignment->user_id
                === $request->user()->id
            && $assignment
                ->role()
                ->where(
                    'name',
                    'superadmin'
                )
                ->exists(),
            422,
            'حذف دسترسی SuperAdmin حساب جاری مجاز نیست.'
        );

        $assignment->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function requirePermission(
        Request $request,
        string $permission
    ): void {
        abort_unless(
            $this->permissions->allows(
                $request->user(),
                $permission,
                null
            ),
            403
        );
    }

    private function userPayload(
        User $user
    ): array {
        return [
            'id' => $user->id,
            'first_name' =>
                $user->first_name,
            'last_name' =>
                $user->last_name,
            'full_name' =>
                trim(
                    "{$user->first_name} {$user->last_name}"
                ),
            'national_code' =>
                $user->national_code,
            'mobile' =>
                $user->mobile,
            'email' =>
                $user->email,
            'mobile_verified_at' =>
                $user->mobile_verified_at,
            'email_verified_at' =>
                $user->email_verified_at,
            'is_active' =>
                $user->is_active,
            'is_blocked' =>
                $user->is_blocked,
            'roles' =>
                $user
                    ->userRoleAssignments
                    ?->map(
                        fn ($assignment) =>
                            $assignment
                                ->role
                                ?->display_name
                    )
                    ->filter()
                    ->values()
                    ->all()
                ?? [],
            'created_at' =>
                $user->created_at,
        ];
    }

    private function rolePayload(
        Role $role
    ): array {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' =>
                $role->display_name,
            'description' =>
                $role->description,
            'is_system' =>
                $role->is_system,
            'permission_ids' =>
                $role->permissions
                    ->pluck('id')
                    ->values()
                    ->all(),
            'permissions_count' =>
                $role->permissions->count(),
            'assignments_count' =>
                $role
                    ->user_role_assignments_count
                ?? $role
                    ->userRoleAssignments()
                    ->count(),
        ];
    }

    private function assignmentPayload(
        UserRoleAssignment $assignment
    ): array {
        return [
            'id' => $assignment->id,
            'user_id' =>
                $assignment->user_id,
            'user_name' =>
                trim(
                    (
                        $assignment->user
                            ?->first_name
                        ?? ''
                    )
                    . ' '
                    . (
                        $assignment->user
                            ?->last_name
                        ?? ''
                    )
                ),
            'role_id' =>
                $assignment->role_id,
            'role_name' =>
                $assignment->role
                    ?->display_name,
            'scope_type' =>
                $this->scopeAlias(
                    $assignment
                        ->scope_type
                ),
            'scope_id' =>
                $assignment->scope_id,
            'starts_at' =>
                $assignment->starts_at,
            'ends_at' =>
                $assignment->ends_at,
            'is_active' =>
                $assignment->is_active,
        ];
    }

    private function validateAssignment(
        Request $request,
        bool $updating = false
    ): array {
        $prefix =
            $updating
                ? 'sometimes'
                : 'required';

        return $request->validate([
            'user_id' => [
                $prefix,
                'integer',
                'exists:users,id',
            ],
            'role_id' => [
                $prefix,
                'integer',
                'exists:roles,id',
            ],
            'scope_type' => [
                $prefix,
                Rule::in([
                    'global',
                    'complex',
                    'building',
                ]),
            ],
            'scope_id' => [
                'nullable',
                'integer',
            ],
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    private function assignmentData(
        Request $request,
        array $data,
        ?UserRoleAssignment $current = null
    ): array {
        $scopeType =
            $data['scope_type']
            ?? $this->scopeAlias(
                $current?->scope_type
            );

        $scopeId =
            array_key_exists(
                'scope_id',
                $data
            )
                ? $data['scope_id']
                : $current?->scope_id;

        [
            $storedType,
            $storedId,
        ] = $this->resolveScope(
            $scopeType,
            $scopeId
        );

        return [
            'user_id' =>
                $data['user_id']
                ?? $current?->user_id,
            'role_id' =>
                $data['role_id']
                ?? $current?->role_id,
            'scope_type' =>
                $storedType,
            'scope_id' =>
                $storedId,
            'starts_at' =>
                array_key_exists(
                    'starts_at',
                    $data
                )
                    ? $data['starts_at']
                    : $current?->starts_at,
            'ends_at' =>
                array_key_exists(
                    'ends_at',
                    $data
                )
                    ? $data['ends_at']
                    : $current?->ends_at,
            'is_active' =>
                $data['is_active']
                ?? $current?->is_active
                ?? true,
            'assigned_by' =>
                $request->user()->id,
        ];
    }

    private function resolveScope(
        string $type,
        ?int $id
    ): array {
        if ($type === 'global') {
            return [
                null,
                null,
            ];
        }

        abort_if(
            $id === null,
            422,
            'برای دسترسی Scoped انتخاب محدوده الزامی است.'
        );

        $model =
            $type === 'building'
                ? Building::query()
                    ->findOrFail($id)
                : Complex::query()
                    ->findOrFail($id);

        return [
            $model->getMorphClass(),
            $model->getKey(),
        ];
    }

    private function scopeAlias(
        ?string $storedType
    ): string {
        if ($storedType === null) {
            return 'global';
        }

        if (
            in_array(
                $storedType,
                [
                    Building::class,
                    (new Building())
                        ->getMorphClass(),
                ],
                true
            )
        ) {
            return 'building';
        }

        if (
            in_array(
                $storedType,
                [
                    Complex::class,
                    (new Complex())
                        ->getMorphClass(),
                ],
                true
            )
        ) {
            return 'complex';
        }

        return $storedType;
    }
}
