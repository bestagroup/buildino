<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileScanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedFileRequest;
use App\Http\Resources\V1\FileResource;
use App\Models\DocumentRecord;
use App\Models\File as ManagedFile;
use App\Models\FileDownload;
use App\Models\MeetingMinute;
use App\Models\User;
use App\Services\Files\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function storeForDocument(
        StoreManagedFileRequest $request,
        DocumentRecord $document,
        FileStorageService $files
    ): JsonResponse {
        $this->authorize('view', $document);
        $this->authorize(
            'create',
            [ManagedFile::class, $document]
        );

        /** @var User $user */
        $user = $request->user();
        $file = $files->store(
            $request->file('file'),
            $user,
            $document,
            $request->safe()->except('file')
        );

        return (new FileResource($file))
            ->response()
            ->setStatusCode(201);
    }

    public function storeForMeetingMinute(
        StoreManagedFileRequest $request,
        MeetingMinute $meetingMinute,
        FileStorageService $files
    ): JsonResponse {
        $this->authorize('view', $meetingMinute);
        $this->authorize(
            'create',
            [ManagedFile::class, $meetingMinute]
        );

        /** @var User $user */
        $user = $request->user();
        $file = $files->store(
            $request->file('file'),
            $user,
            $meetingMinute,
            $request->safe()->except('file')
        );

        return (new FileResource($file))
            ->response()
            ->setStatusCode(201);
    }

    public function download(
        Request $request,
        ManagedFile $file
    ): StreamedResponse {
        $this->authorize('view', $file);

        abort_if(
            $file->expires_at?->isPast(),
            410,
            'File has expired.'
        );

        abort_unless(
            $file->scan_status === FileScanStatus::Clean,
            423,
            'File is not available for download.'
        );

        abort_unless(
            Storage::disk($file->disk)->exists($file->path),
            404,
            'File content was not found.'
        );

        FileDownload::query()->create([
            'file_id' => $file->getKey(),
            'user_id' => $request->user()?->getKey(),
            'ip_address' => $request->ip(),
            'downloaded_at' => now(),
        ]);

        return Storage::disk($file->disk)->download(
            $file->path,
            $file->original_name,
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function destroy(
        ManagedFile $file,
        FileStorageService $files
    ): JsonResponse {
        $this->authorize('delete', $file);
        $files->delete($file);

        return response()->json(status: 204);
    }
}
