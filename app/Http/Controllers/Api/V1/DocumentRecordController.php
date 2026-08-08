<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DocumentRecord\CreateDocumentRecord;
use App\Actions\DocumentRecord\UpdateDocumentRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRecordRequest;
use App\Http\Requests\UpdateDocumentRecordRequest;
use App\Models\DocumentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentRecord::class);

        $items = DocumentRecord::query()
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(StoreDocumentRecordRequest $request, CreateDocumentRecord $action): JsonResponse
    {
        $this->authorize('create', DocumentRecord::class);
        $model = $action->execute($request->validated());

        return response()->json(['data' => $model], 201);
    }

    public function show(DocumentRecord $model): JsonResponse
    {
        $this->authorize('view', $model);
        return response()->json(['data' => $model]);
    }

    public function update(UpdateDocumentRecordRequest $request, DocumentRecord $model, UpdateDocumentRecord $action): JsonResponse
    {
        $this->authorize('update', $model);
        return response()->json(['data' => $action->execute($model, $request->validated())]);
    }

    public function destroy(DocumentRecord $model): JsonResponse
    {
        $this->authorize('delete', $model);
        $model->delete();
        return response()->json(status: 204);
    }
}
