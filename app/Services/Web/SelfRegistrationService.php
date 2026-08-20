<?php

namespace App\Services\Web;

use App\Enums\OccupancyType;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Role;
use App\Models\UnitInvitation;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\UnitInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SelfRegistrationService
{
    public function __construct(
        private readonly UnitInvitationService $invitations
    ) {}

    public function assertRegistrationCanProceed(
        array $data
    ): void {
        $this->personaConfiguration(
            (string) ($data['persona'] ?? '')
        );

        $this->role(
            (string) $data['persona']
        );

        if (
            $this->personaKind($data)
                === 'resident'
        ) {
            $this->residentInvitation($data);
        }
    }

    /**
     * @return array{user: User, destination: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(
            function () use ($data): array {
                $this->assertRegistrationCanProceed(
                    $data
                );

                $this->assertIdentityIsAvailable(
                    $data
                );

                $role = $this->role(
                    (string) $data['persona']
                );

                $user = User::query()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'mobile' => $data['mobile'],
                    'email' => $data['email'] ?? null,
                    'mobile_verified_at' => now(),
                    'email_verified_at' => null,
                    'password' => $data['password_hash'],
                    'is_active' => true,
                    'is_blocked' => false,
                ]);

                $kind = $this->personaKind(
                    $data
                );

                if ($kind === 'management') {
                    $scope = $this->createWorkspace(
                        $data
                    );

                    $this->assignRole(
                        $user,
                        $role,
                        $scope
                    );

                    return [
                        'user' => $user,
                        'destination' => 'management.dashboard',
                    ];
                }

                if ($kind === 'provider') {
                    $this->assignRole(
                        $user,
                        $role,
                        null
                    );

                    return [
                        'user' => $user,
                        'destination' => 'portal.provider.dashboard',
                    ];
                }

                $invitation = $this->invitations
                    ->accept(
                        (string) $data[
                            'invitation_token'
                        ],
                        $user
                    );

                $this->assignRole(
                    $user,
                    $role,
                    $invitation->unit
                );

                return [
                    'user' => $user,
                    'destination' => 'portal.resident.dashboard',
                ];
            },
            3
        );
    }

    public function personaForInvitation(
        string $rawToken
    ): string {
        $invitation = UnitInvitation::query()
            ->where(
                'token',
                hash('sha256', $rawToken)
            )
            ->first();

        if (
            ! $invitation
            || ! $invitation->isActive()
        ) {
            return 'tenant';
        }

        return $invitation->relation_type
            === OccupancyType::Owner
                ? 'owner'
                : 'tenant';
    }

    private function createWorkspace(
        array $data
    ): Model {
        $complex = Complex::query()->create([
            'code' => $this->uniqueCode(
                Complex::class,
                'CMP'
            ),
            'title' => $data['complex_title'],
            'province' => $data['province'],
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->getKey(),
            'code' => $this->uniqueCode(
                Building::class,
                'BLD'
            ),
            'title' => $data['building_title'],
            'address' => $data['address'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'floors_count' => 0,
            'units_count' => 0,
            'parking_count' => 0,
            'storage_count' => 0,
            'is_active' => true,
        ]);

        $scope = $this->personaConfiguration(
            (string) $data['persona']
        )['scope'] ?? 'building';

        return $scope === 'complex'
            ? $complex
            : $building;
    }

    private function residentInvitation(
        array $data
    ): UnitInvitation {
        $candidate = new User([
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
        ]);

        try {
            $invitation = $this->invitations
                ->findForUserByToken(
                    (string) (
                        $data[
                            'invitation_token'
                        ] ?? ''
                    ),
                    $candidate
                );
        } catch (
            AuthorizationException|ModelNotFoundException
        ) {
            throw ValidationException::withMessages([
                'invitation_token' => [
                    'کد دعوت معتبر نیست یا با شماره موبایل/ایمیل شما مطابقت ندارد.',
                ],
            ]);
        }

        if (! $invitation->isActive()) {
            throw ValidationException::withMessages([
                'invitation_token' => [
                    'کد دعوت منقضی شده یا قبلاً استفاده شده است.',
                ],
            ]);
        }

        $configuration =
            $this->personaConfiguration(
                (string) $data['persona']
            );

        $allowedTypes = $configuration[
            'invitation_types'
        ] ?? [];

        $relationType =
            $invitation->relation_type
                instanceof OccupancyType
                    ? $invitation
                        ->relation_type
                        ->value
                    : (string) $invitation
                        ->relation_type;

        if (
            ! in_array(
                $relationType,
                $allowedTypes,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'persona' => [
                    'نوع حساب انتخاب‌شده با نوع دعوت واحد مطابقت ندارد.',
                ],
            ]);
        }

        return $invitation;
    }

    private function assignRole(
        User $user,
        Role $role,
        ?Model $scope
    ): void {
        UserRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->getKey(),
            'starts_at' => now(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => $user->getKey(),
        ]);
    }

    private function assertIdentityIsAvailable(
        array $data
    ): void {
        if (
            User::query()
                ->where('mobile', $data['mobile'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'mobile' => [
                    'این شماره موبایل قبلاً ثبت شده است؛ از صفحه ورود استفاده کنید.',
                ],
            ]);
        }

        if (
            filled($data['email'] ?? null)
            && User::query()
                ->where('email', $data['email'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'این ایمیل قبلاً ثبت شده است.',
                ],
            ]);
        }
    }

    private function role(string $persona): Role
    {
        $role = Role::query()
            ->where('name', $persona)
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'persona' => [
                    'نقش انتخاب‌شده هنوز در سامانه فعال نشده است. لطفاً با پشتیبانی تماس بگیرید.',
                ],
            ]);
        }

        return $role;
    }

    private function personaKind(array $data): string
    {
        return (string) (
            $this->personaConfiguration(
                (string) ($data['persona'] ?? '')
            )['kind'] ?? ''
        );
    }

    private function personaConfiguration(
        string $persona
    ): array {
        $configuration = config(
            "self_registration.personas.{$persona}"
        );

        if (! is_array($configuration)) {
            throw ValidationException::withMessages([
                'persona' => [
                    'نوع حساب انتخاب‌شده قابل ثبت‌نام عمومی نیست.',
                ],
            ]);
        }

        return $configuration;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueCode(
        string $model,
        string $prefix
    ): string {
        do {
            $code = $prefix.'-'.Str::upper(
                Str::random(10)
            );
        } while (
            $model::query()
                ->where('code', $code)
                ->exists()
        );

        return $code;
    }
}
