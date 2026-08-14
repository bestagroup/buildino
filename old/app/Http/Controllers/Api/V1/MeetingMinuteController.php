<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MeetingMinute\CreateMeetingMinute;
use App\Actions\MeetingMinute\UpdateMeetingMinute;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingMinuteRequest;
use App\Http\Requests\UpdateMeetingMinuteRequest;
use App\Models\MeetingMinute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingMinuteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MeetingMinute::class);

        $items = MeetingMinute::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreMeetingMinuteRequest $request, CreateMeetingMinute $action): JsonResponse
    {
        $this->authorize('create', MeetingMinute::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(MeetingMinute $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateMeetingMinuteRequest $request, MeetingMinute $model, UpdateMeetingMinute $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(MeetingMinute $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
