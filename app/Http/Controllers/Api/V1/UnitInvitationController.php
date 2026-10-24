<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptUnitInvitationRequest;
use App\Http\Requests\ResolveUnitInvitationRequest;
use App\Http\Requests\StoreUnitInvitationRequest;
use App\Http\Resources\V1\UnitInvitationResource;
use App\Models\Unit;
use App\Models\UnitInvitation;
use App\Models\User;
use App\Services\UnitInvitationService;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UnitInvitationController extends Controller
{
    public function index(
        Request $request,
        Unit $unit,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $building = $this->resolveBuilding(
            $unit
        );

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'unit-invitations.view',
                $building
            ),
            403
        );

        $query = $unit->unitInvitations()
            ->with([
                'invitedBy:id,first_name,last_name',
                'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
                'acceptedUser:id,first_name,last_name,mobile,email',
            ]);

        if ($status = $request->query('status')) {
            $query->where(
                'status',
                $status
            );
        }

        if ($channel = $request->query('channel')) {
            $query->where(
                'channel',
                $channel
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return UnitInvitationResource::collection(
            $query
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreUnitInvitationRequest $request,
        Unit $unit,
        UnitInvitationService $service,
        PermissionChecker $permissions
    ) {
        $building = $this->resolveBuilding(
            $unit
        );

        abort_unless(
            $building
            && $permissions->allows(
                $request->user(),
                'unit-invitations.create',
                $building
            ),
            403
        );

        $data = $request->validated();

        if (isset($data['occupancy'])) {
            abort_unless($permissions->allows($request->user(), 'unit-occupancies.create', $building), 403);

            $recipient = User::query()->whereKey(collect($data['user_ids'])->first())
                ->where('is_active', true)->where('is_blocked', false)
                ->whereNotNull('mobile')->where('mobile', '<>', '')->first();
            if (! $recipient) {
                throw ValidationException::withMessages(['user_ids' => ['حساب فعال با شماره موبایل معتبر پیدا نشد.']]);
            }

            $result = $service->createForPendingOccupancy(
                $unit, $request->user(), $recipient, $data['occupancy'], $data
            );
            $invitation = $result['invitation']->load([
                'unit:id,floor_id,unit_number,title',
                'invitedBy:id,first_name,last_name',
                'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            ]);
            $response = ['data' => [(new UnitInvitationResource($invitation))->resolve($request)], 'meta' => ['sent_count' => 1]];
            if (app()->environment(['local', 'testing'])) {
                $response['meta']['accept_tokens'] = [(string) $recipient->id => $result['raw_token']];
                $response['meta']['invitation_codes'] = [(string) $recipient->id => $result['invitation_code']];
            }

            return response()->json($response, 201);
        }

        /*
         * Recipient identity, mobile and unit relationship are already stored
         * in Buildino. Resolve them from the selected unit instead of asking
         * the manager to enter duplicate information.
         */
        $activeRelation = function ($query): void {
            $query
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereNull('starts_at')
                        ->orWhere(
                            'starts_at',
                            '<=',
                            now()->toDateString()
                        );
                })
                ->where(function ($query): void {
                    $query
                        ->whereNull('ends_at')
                        ->orWhere(
                            'ends_at',
                            '>=',
                            now()->toDateString()
                        );
                });
        };

        $linkedUserIds = $unit
            ->unitOwnerships()
            ->where($activeRelation)
            ->pluck('user_id')
            ->merge(
                $unit
                    ->unitOccupancies()
                    ->where($activeRelation)
                    ->pluck('user_id')
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(
                fn (int $id): bool =>
                    $id === (int) $request
                        ->user()
                        ->getKey()
            )
            ->values();

        $requestedUserId = isset($data['user_id'])
            ? (int) $data['user_id']
            : null;

        if (
            $requestedUserId !== null
            && ! $linkedUserIds->contains(
                $requestedUserId
            )
        ) {
            throw ValidationException::withMessages([
                'user_id' => [
                    'کاربر انتخاب‌شده به واحد انتخاب‌شده متصل نیست.',
                ],
            ]);
        }

        if ($requestedUserId === null) {
            if ($linkedUserIds->count() === 1) {
                $requestedUserId = (int) $linkedUserIds->first();
            } elseif ($linkedUserIds->count() > 1) {
                throw ValidationException::withMessages([
                    'user_id' => [
                        'برای این واحد بیش از یک کاربر ثبت شده است؛ کاربر دریافت‌کننده دعوت را انتخاب کنید.',
                    ],
                ]);
            }
        }

        $recipientIds = $requestedUserId !== null
            ? collect([$requestedUserId])
            : collect();

        $users = User::query()
            ->whereIn('id', $recipientIds->all())
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->whereNotNull('mobile')
            ->where('mobile', '<>', '')
            ->get();

        /*
         * When recipients are explicitly selected, every selected user must
         * be deliverable. When only the unit is selected, silently exclude
         * linked users that do not yet have an active SMS-capable account.
         */
        if (
            $requestedUserId !== null
            && $users->count()
                !== $recipientIds->count()
        ) {
            throw ValidationException::withMessages([
                'user_id' => [
                    'برای کاربر انتخاب‌شده، حساب فعال یا شماره موبایل معتبر ثبت نشده است.',
                ],
            ]);
        }

        if ($users->isEmpty()) {
            throw ValidationException::withMessages([
                'user_id' => [
                    'برای این واحد کاربر فعالی با شماره موبایل ثبت نشده است.',
                ],
            ]);
        }

        $results = $service->createForLinkedUsers(
            $unit,
            $request->user(),
            $users,
            $data
        );

        $invitations = collect($results)
            ->pluck('invitation');

        $invitations->each(
            fn (UnitInvitation $invitation) => $invitation->load([
                'unit:id,floor_id,unit_number,title',
                'invitedBy:id,first_name,last_name',
                'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
                'acceptedUser:id,first_name,last_name,mobile,email',
            ])
        );

        $response = [
            'data' => $invitations
                ->map(
                    fn (UnitInvitation $invitation): array => (
                        new UnitInvitationResource($invitation)
                    )->resolve($request)
                )
                ->values(),
            'meta' => [
                'sent_count' => $invitations->count(),
            ],
        ];

        if (app()->environment(['local', 'testing'])) {
            $response['meta']['accept_tokens'] = collect($results)
                ->mapWithKeys(
                    fn (array $result): array => [
                        (string) (
                            $result['invitation']->invited_user_id
                            ?: $result['invitation']->getKey()
                        ) => $result['raw_token'],
                    ]
                );

            $response['meta']['invitation_codes'] = collect($results)
                ->mapWithKeys(
                    fn (array $result): array => [
                        (string) (
                            $result['invitation']->invited_user_id
                            ?: $result['invitation']->getKey()
                        ) => $result['invitation_code'],
                    ]
                );
        }

        return response()->json($response, 201);
    }

    public function show(
        UnitInvitation $unitInvitation
    ): UnitInvitationResource {
        $this->authorize(
            'view',
            $unitInvitation
        );

        $unitInvitation->load([
            'unit:id,floor_id,unit_number,title',
            'invitedBy:id,first_name,last_name',
            'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            'acceptedUser:id,first_name,last_name,mobile,email',
        ]);

        return new UnitInvitationResource(
            $unitInvitation
        );
    }

    public function resend(
        Request $request,
        UnitInvitation $unitInvitation,
        UnitInvitationService $service
    ) {
        $this->authorize(
            'update',
            $unitInvitation
        );

        $result = $service->resend(
            $unitInvitation
        );

        $invitation = $result['invitation'];

        $invitation->load([
            'unit:id,floor_id,unit_number,title',
            'invitedBy:id,first_name,last_name',
            'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            'acceptedUser:id,first_name,last_name,mobile,email',
        ]);

        $response = (
            new UnitInvitationResource($invitation)
        )->response();

        if (
            app()->environment([
                'local',
                'testing',
            ])
        ) {
            $response->setData([
                'data' => (
                    new UnitInvitationResource($invitation)
                )->resolve($request),

                'meta' => [
                    'accept_token' => $result['raw_token'],
                    'invitation_code' => $result['invitation_code'],
                ],
            ]);
        }

        return $response;
    }

    public function cancel(
        UnitInvitation $unitInvitation,
        UnitInvitationService $service
    ): UnitInvitationResource {
        $this->authorize(
            'update',
            $unitInvitation
        );

        $unitInvitation = $service->cancel(
            $unitInvitation
        );

        $unitInvitation->load([
            'unit:id,floor_id,unit_number,title',
            'invitedBy:id,first_name,last_name',
            'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            'acceptedUser:id,first_name,last_name,mobile,email',
        ]);

        return new UnitInvitationResource(
            $unitInvitation
        );
    }

    public function resolve(
        ResolveUnitInvitationRequest $request,
        UnitInvitationService $service
    ): UnitInvitationResource {
        $invitation = $service->findForUserByToken(
            $request->validated('token'),
            $request->user()
        );

        $invitation->load([
            'unit:id,floor_id,unit_number,title',
            'invitedBy:id,first_name,last_name',
            'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            'acceptedUser:id,first_name,last_name,mobile,email',
        ]);

        return new UnitInvitationResource(
            $invitation
        );
    }

    public function accept(
        AcceptUnitInvitationRequest $request,
        UnitInvitationService $service
    ): UnitInvitationResource {
        $invitation = $service->accept(
            $request->validated('token'),
            $request->user()
        );

        $invitation->load([
            'unit:id,floor_id,unit_number,title',
            'invitedBy:id,first_name,last_name',
            'invitedUser:id,first_name,last_name,mobile,email,is_active,is_blocked,mobile_verified_at,email_verified_at',
            'acceptedUser:id,first_name,last_name,mobile,email',
        ]);

        return new UnitInvitationResource(
            $invitation
        );
    }

    private function resolveBuilding(
        Unit $unit
    ) {
        $unit->loadMissing(
            'floor.block.building'
        );

        return $unit->floor?->block?->building;
    }
}
