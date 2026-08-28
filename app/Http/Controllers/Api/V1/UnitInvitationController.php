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
use App\Services\Web\ScopedUserManagementService;
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
                'invitedUser:id,first_name,last_name,mobile,email',
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
        PermissionChecker $permissions,
        ScopedUserManagementService $scopedUsers
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

        $requestedIds = collect($data['user_ids'])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $query = User::query()
            ->whereIn('id', $requestedIds->all())
            ->whereKeyNot($request->user()->getKey())
            ->where('is_active', true)
            ->where('is_blocked', false);

        $scopedUsers->applyVisibleUsers(
            $query,
            $request->user(),
            'users.view'
        );

        $users = $query->get();

        if ($users->count() !== $requestedIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => [
                    'یک یا چند کاربر انتخاب‌شده خارج از محدوده دسترسی شما هستند.',
                ],
            ]);
        }

        $results = $service->createForUsers(
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
                'invitedUser:id,first_name,last_name,mobile,email',
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
                        (string) $result['invitation']->invited_user_id =>
                            $result['raw_token'],
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
            'invitedUser:id,first_name,last_name,mobile,email',
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
            'invitedUser:id,first_name,last_name,mobile,email',
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
            'invitedUser:id,first_name,last_name,mobile,email',
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
            'invitedUser:id,first_name,last_name,mobile,email',
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
            'invitedUser:id,first_name,last_name,mobile,email',
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
