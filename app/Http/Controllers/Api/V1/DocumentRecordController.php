<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DocumentRecord\CreateDocumentRecord;
use App\Actions\DocumentRecord\UpdateDocumentRecord;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRecordRequest;
use App\Http\Requests\UpdateDocumentRecordRequest;
use App\Models\DocumentRecord;
use App\Models\User;
use App\Services\Documents\DocumentRecordScopeService;
use App\Services\Documents\DocumentTargetResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRecordController extends Controller
{
    public function index(
        Request $request,
        DocumentRecordScopeService $scope
    ): JsonResponse {
        $this->authorize('viewAny', DocumentRecord::class);

        /** @var User $user */
        $user = $request->user();

        $items = $scope->apply(
            DocumentRecord::query()
                ->with([
                    'documentable',
                    'fileRelations.file',
                ]),
            $user,
            'documents.view'
        )
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 100));

        return response()->json($items);
    }

    public function store(
        StoreDocumentRecordRequest $request,
        CreateDocumentRecord $action,
        DocumentTargetResolver $targets
    ): JsonResponse {
        $data = $request->validated();
        $target = $targets->resolve(
            $data['documentable_type'],
            (int) $data['documentable_id']
        );

        $this->authorize(
            'create',
            [DocumentRecord::class, $target]
        );

        $data['documentable_type'] =
            $targets->normalizedType($target);

        /** @var User $user */
        $user = $request->user();
        $document = $action->execute($data, $user);

        return response()->json(['data' => $document], 201);
    }

    public function show(DocumentRecord $document): JsonResponse
    {
        $this->authorize('view', $document);
        return response()->json([
            'data' => $document->load([
                'documentable',
                'fileRelations.file',
            ]),
        ]);
    }

    public function update(UpdateDocumentRecordRequest $request, DocumentRecord $document, UpdateDocumentRecord $action): JsonResponse
    {
        $this->authorize('update', $document);
        return response()->json(['data' => $action->execute($document, $request->validated())]);
    }

    public function destroy(DocumentRecord $document): JsonResponse
    {
        $this->authorize('delete', $document);
        $document->delete();
        return response()->json(status: 204);
    }
}
