<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MeetingMinute\CreateMeetingMinute;
use App\Actions\MeetingMinute\UpdateMeetingMinute;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingMinuteRequest;
use App\Http\Requests\UpdateMeetingMinuteRequest;
use App\Models\Building;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Services\Security\BuildingResourceScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingMinuteController extends Controller
{
    public function index(
        Request $request,
        BuildingResourceScopeService $scope
    ): JsonResponse {
        $this->authorize('viewAny', MeetingMinute::class);

        /** @var User $user */
        $user = $request->user();

        $items = $scope->apply(
            MeetingMinute::query()->with('fileRelations.file'),
            $user,
            'meeting-minutes.view'
        )
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreMeetingMinuteRequest $request, CreateMeetingMinute $action): JsonResponse
    {
        $data = $request->validated();
        $building = Building::query()->findOrFail(
            $data['building_id']
        );

        $this->authorize(
            'create',
            [MeetingMinute::class, $building]
        );

        /** @var User $user */
        $user = $request->user();
        $meetingMinute = $action->execute($data, $user);

        return response()->json(['data' => $meetingMinute], 201);
    }

    public function show(MeetingMinute $meetingMinute): JsonResponse
    {
        $this->authorize('view', $meetingMinute);
        return response()->json([
            'data' => $meetingMinute->load('fileRelations.file'),
        ]);
    }

    public function update(UpdateMeetingMinuteRequest $request, MeetingMinute $meetingMinute, UpdateMeetingMinute $action): JsonResponse
    {
        $this->authorize('update', $meetingMinute);
        return response()->json(['data' => $action->execute($meetingMinute, $request->validated())]);
    }

    public function destroy(MeetingMinute $meetingMinute): JsonResponse
    {
        $this->authorize('delete', $meetingMinute);
        $meetingMinute->delete();
        return response()->json(status: 204);
    }
}
