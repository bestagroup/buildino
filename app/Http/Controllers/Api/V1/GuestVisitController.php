<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordGuestAccessRequest;
use App\Http\Requests\StoreGuestVisitRequest;
use App\Http\Requests\UpdateGuestVisitRequest;
use App\Http\Resources\V1\GuestAccessLogResource;
use App\Http\Resources\V1\GuestVisitResource;
use App\Models\GuestVisit;
use App\Models\Unit;
use App\Services\GuestVisitService;
use App\Services\Security\GuestVisitAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestVisitController extends Controller
{
    public function index(
        Request $request,
        Unit $unit,
        GuestVisitAccessService $access
    ): AnonymousResourceCollection {
        abort_unless(
            $access->allowsForUnit(
                $request->user(),
                $unit,
                'view'
            ),
            403
        );

        $query = $unit->guestVisits()
            ->with([
                'guest',
                'registeredBy:id,first_name,last_name',
            ])
            ->withCount('guestAccessLogs');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($request->filled('from')) {
            $query->where(
                'expected_entry_at',
                '>=',
                $request->query('from')
            );
        }

        if ($request->filled('to')) {
            $query->where(
                'expected_entry_at',
                '<=',
                $request->query('to')
            );
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas(
                'guest',
                function (Builder $query) use ($search): void {
                    $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('national_code', 'like', "%{$search}%")
                        ->orWhere('vehicle_plate', 'like', "%{$search}%");
                }
            );
        }

        $perPage = min(
            max($request->integer('per_page', 20), 1),
            100
        );

        return GuestVisitResource::collection(
            $query
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(
        StoreGuestVisitRequest $request,
        Unit $unit,
        GuestVisitService $service,
        GuestVisitAccessService $access
    ) {
        abort_unless(
            $access->allowsForUnit(
                $request->user(),
                $unit,
                'create'
            ),
            403
        );

        $visit = $service->register(
            $unit,
            $request->user(),
            $request->validated()
        );

        $visit->load([
            'guest',
            'unit:id,floor_id,unit_number,title',
            'registeredBy:id,first_name,last_name',
        ]);

        $visit->loadCount('guestAccessLogs');

        return (new GuestVisitResource($visit))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        GuestVisit $guestVisit,
        GuestVisitAccessService $access
    ): GuestVisitResource {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'view'
            ),
            403
        );

        $guestVisit->load([
            'guest',
            'unit:id,floor_id,unit_number,title',
            'registeredBy:id,first_name,last_name',
        ]);

        $guestVisit->loadCount('guestAccessLogs');

        return new GuestVisitResource($guestVisit);
    }

    public function update(
        UpdateGuestVisitRequest $request,
        GuestVisit $guestVisit,
        GuestVisitService $service,
        GuestVisitAccessService $access
    ): GuestVisitResource {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'update'
            ),
            403
        );

        $guestVisit = $service->update(
            $guestVisit,
            $request->validated()
        );

        $guestVisit->load([
            'guest',
            'unit:id,floor_id,unit_number,title',
            'registeredBy:id,first_name,last_name',
        ]);

        $guestVisit->loadCount('guestAccessLogs');

        return new GuestVisitResource($guestVisit);
    }

    public function cancel(
        Request $request,
        GuestVisit $guestVisit,
        GuestVisitService $service,
        GuestVisitAccessService $access
    ): GuestVisitResource {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'update'
            ),
            403
        );

        $guestVisit = $service->cancel($guestVisit);

        $guestVisit->load([
            'guest',
            'unit:id,floor_id,unit_number,title',
            'registeredBy:id,first_name,last_name',
        ]);

        $guestVisit->loadCount('guestAccessLogs');

        return new GuestVisitResource($guestVisit);
    }

    public function accessLogs(
        Request $request,
        GuestVisit $guestVisit,
        GuestVisitAccessService $access
    ): AnonymousResourceCollection {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'view'
            ),
            403
        );

        $logs = $guestVisit
            ->guestAccessLogs()
            ->with('verifiedBy:id,first_name,last_name')
            ->orderByDesc('occurred_at')
            ->paginate(
                min(
                    max($request->integer('per_page', 20), 1),
                    100
                )
            )
            ->withQueryString();

        return GuestAccessLogResource::collection($logs);
    }

    public function entry(
        RecordGuestAccessRequest $request,
        GuestVisit $guestVisit,
        GuestVisitService $service,
        GuestVisitAccessService $access
    ) {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'update',
                false
            ),
            403
        );

        $log = $service->recordEntry(
            $guestVisit,
            $request->user(),
            $request->validated()
        );

        $log->load('verifiedBy:id,first_name,last_name');

        return (new GuestAccessLogResource($log))
            ->response()
            ->setStatusCode(200);
    }

    public function exit(
        RecordGuestAccessRequest $request,
        GuestVisit $guestVisit,
        GuestVisitService $service,
        GuestVisitAccessService $access
    ) {
        abort_unless(
            $access->allowsForVisit(
                $request->user(),
                $guestVisit,
                'update',
                false
            ),
            403
        );

        $log = $service->recordExit(
            $guestVisit,
            $request->user(),
            $request->validated()
        );

        $log->load('verifiedBy:id,first_name,last_name');

        return (new GuestAccessLogResource($log))
            ->response()
            ->setStatusCode(200);
    }
}
