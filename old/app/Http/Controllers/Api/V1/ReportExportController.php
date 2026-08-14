<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateReportExportRequest;
use App\Http\Resources\V1\GeneratedReportResource;
use App\Jobs\Reports\GenerateReportJob;
use App\Models\Building;
use App\Models\FileDownload;
use App\Models\GeneratedReport;
use App\Models\ReportDefinition;
use App\Services\Reports\GeneratedReportService;
use App\Services\Reports\ReportDataResolver;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(
        Request $request,
        PermissionChecker $permissions
    ): AnonymousResourceCollection {
        $query = GeneratedReport::query()
            ->with([
                'reportDefinition',
                'file',
            ])
            ->latest('id');

        $buildingId =
            $request->integer('building_id');

        if ($buildingId > 0) {
            $building = Building::query()
                ->findOrFail($buildingId);

            $allowed =
                $permissions->allows(
                    $request->user(),
                    'generated-reports.view',
                    $building
                );

            if (! $allowed) {
                $query->where(
                    'generated_by',
                    $request->user()->getKey()
                );
            }

            $query->where(
                'building_id',
                $building->getKey()
            );
        } elseif (
            ! $permissions->allows(
                $request->user(),
                'generated-reports.view',
                null
            )
        ) {
            $query->where(
                'generated_by',
                $request->user()->getKey()
            );
        }

        if (
            $request->filled('status')
            && in_array(
                $request->string('status')->toString(),
                ReportStatus::values(),
                true
            )
        ) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        $perPage = min(
            100,
            max(
                1,
                $request->integer(
                    'per_page',
                    20
                )
            )
        );

        return GeneratedReportResource::collection(
            $query->paginate($perPage)
        );
    }

    public function store(
        GenerateReportExportRequest $request,
        ReportDefinition $reportDefinition,
        GeneratedReportService $service,
        ReportDataResolver $resolver,
        PermissionChecker $permissions
    ) {
        $scope = $resolver->scope(
            $reportDefinition
        );

        $building = null;

        if ($scope === 'building') {
            if (! $request->filled('building_id')) {
                abort(
                    422,
                    'building_id is required for Building reports.'
                );
            }

            $building = Building::query()
                ->findOrFail(
                    $request->integer(
                        'building_id'
                    )
                );
        }

        $permission =
            $reportDefinition
                ->configuration['permission']
            ?? (
                $scope === 'platform'
                    ? 'reports.platform.view'
                    : 'reports.view'
            );

        abort_unless(
            $permissions->allows(
                $request->user(),
                $permission,
                $building
            ),
            403
        );

        abort_unless(
            $permissions->allows(
                $request->user(),
                'generated-reports.create',
                $building
            ),
            403
        );

        $report = $service->request(
            $reportDefinition,
            $building,
            $request->user(),
            ReportFormat::from(
                $request->validated(
                    'format'
                )
            ),
            $request->validated()
        );

        $report->load([
            'reportDefinition',
            'file',
        ]);

        return (new GeneratedReportResource(
            $report
        ))
            ->response()
            ->setStatusCode(202);
    }

    public function show(
        Request $request,
        GeneratedReport $generatedReport,
        PermissionChecker $permissions
    ): GeneratedReportResource {
        $this->authorizeReportAccess(
            $request,
            $generatedReport,
            $permissions
        );

        $generatedReport->load([
            'reportDefinition',
            'file',
        ]);

        return new GeneratedReportResource(
            $generatedReport
        );
    }

    public function retry(
        Request $request,
        GeneratedReport $generatedReport,
        PermissionChecker $permissions
    ): GeneratedReportResource {
        $this->authorizeReportAccess(
            $request,
            $generatedReport,
            $permissions,
            'generated-reports.update'
        );

        if (
            $generatedReport->status
            === ReportStatus::Completed
        ) {
            abort(
                422,
                'Completed reports do not require retry.'
            );
        }

        if (
            $generatedReport->status
            === ReportStatus::Processing
        ) {
            abort(
                409,
                'Report export is currently processing.'
            );
        }

        $generatedReport->update([
            'status' =>
                ReportStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ]);

        GenerateReportJob::dispatch(
            $generatedReport->getKey()
        );

        $generatedReport->load([
            'reportDefinition',
            'file',
        ]);

        return new GeneratedReportResource(
            $generatedReport->refresh()
        );
    }

    public function download(
        Request $request,
        GeneratedReport $generatedReport,
        PermissionChecker $permissions
    ): StreamedResponse {
        $this->authorizeReportAccess(
            $request,
            $generatedReport,
            $permissions
        );

        $generatedReport->loadMissing(
            'file'
        );

        if (
            $generatedReport->status
                !== ReportStatus::Completed
            || ! $generatedReport->file
        ) {
            abort(
                409,
                'Report export is not ready for download.'
            );
        }

        $file = $generatedReport->file;

        if (
            $file->expires_at
            && $file->expires_at->isPast()
        ) {
            abort(
                410,
                'Report export has expired.'
            );
        }

        if (
            ! Storage::disk($file->disk)
                ->exists($file->path)
        ) {
            abort(
                410,
                'Report export file is no longer available.'
            );
        }

        FileDownload::query()->create([
            'file_id' =>
                $file->getKey(),

            'user_id' =>
                $request
                    ->user()
                    ->getKey(),

            'ip_address' =>
                $request->ip(),

            'downloaded_at' =>
                now(),
        ]);

        return Storage::disk(
            $file->disk
        )->download(
            $file->path,
            $file->original_name,
            [
                'Content-Type' =>
                    $file->mime_type
                    ?? 'application/octet-stream',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    public function destroy(
        Request $request,
        GeneratedReport $generatedReport,
        PermissionChecker $permissions
    ): \Illuminate\Http\Response {
        $this->authorizeReportAccess(
            $request,
            $generatedReport,
            $permissions,
            'generated-reports.delete'
        );

        $generatedReport->loadMissing(
            'file'
        );

        $file = $generatedReport->file;

        if ($file) {
            Storage::disk($file->disk)
                ->delete($file->path);

            $file->delete();
        }

        $generatedReport->delete();

        return response()->noContent();
    }

    private function authorizeReportAccess(
        Request $request,
        GeneratedReport $report,
        PermissionChecker $permissions,
        string $permission =
            'generated-reports.view'
    ): void {
        if (
            (int) $report->generated_by
            === (int) $request
                ->user()
                ->getKey()
        ) {
            return;
        }

        $scope = $report->building_id
            ? Building::query()->find(
                $report->building_id
            )
            : null;

        abort_unless(
            $permissions->allows(
                $request->user(),
                $permission,
                $scope
            ),
            403
        );
    }
}
