<?php

namespace Tests\Feature\Reports;

use App\Enums\FileScanStatus;
use App\Enums\FileVisibility;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Jobs\Reports\GenerateReportJob;
use App\Models\File;
use App\Models\GeneratedReport;
use App\Models\ReportDefinition;
use App\Services\Reports\Export\ExcelReportWriter;
use App\Services\Reports\Export\PdfReportWriter;
use App\Services\Reports\GeneratedReportService;
use App\Services\Reports\ReportDataResolver;
use App\Services\Reports\Export\ReportExportWriterFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ReportExportFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_export_request_creates_pending_report_with_normalized_filters(): void
    {
        Queue::fake();

        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $definition = $this->definition(
            'building.financial_summary'
        );

        $report = app(
            GeneratedReportService::class
        )->request(
            $definition,
            $graph['building'],
            $user,
            ReportFormat::Csv,
            [
                'from' =>
                    now()->toDateString(),
                'to' =>
                    now()->toDateString(),
            ]
        );

        $this->assertSame(
            ReportStatus::Pending,
            $report->status
        );

        $this->assertSame(
            ReportFormat::Csv,
            $report->format
        );

        $this->assertSame(
            now()->toDateString(),
            $report->filters['from']
        );

        $this->assertSame(
            now()->toDateString(),
            $report->filters['to']
        );
    }

    public function test_csv_job_generates_private_file_and_completes_report(): void
    {
        Storage::fake('local');

        config([
            'report_exports.disk' => 'local',
            'report_exports.retention_days' => 7,
        ]);

        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $report = GeneratedReport::query()->create([
            'report_definition_id' =>
                $this->definition(
                    'building.financial_summary'
                )->id,
            'building_id' =>
                $graph['building']->id,
            'generated_by' =>
                $user->id,
            'format' =>
                ReportFormat::Csv,
            'status' =>
                ReportStatus::Pending,
            'filters' => [
                'from' =>
                    now()->toDateString(),
                'to' =>
                    now()->toDateString(),
            ],
        ]);

        $job = new GenerateReportJob(
            $report->id
        );

        $job->handle(
            app(ReportDataResolver::class),
            app(
                ReportExportWriterFactory::class
            )
        );

        $report = $report->fresh([
            'file',
        ]);

        $this->assertSame(
            ReportStatus::Completed,
            $report->status
        );

        $this->assertNotNull(
            $report->file
        );

        $this->assertSame(
            FileVisibility::Private,
            $report->file->visibility
        );

        $this->assertSame(
            FileScanStatus::Clean,
            $report->file->scan_status
        );

        $this->assertSame(
            'generated_report',
            $report->file->category
        );

        $this->assertNotNull(
            $report->file->checksum
        );

        $this->assertTrue(
            $report->file->expires_at->isFuture()
        );

        Storage::disk('local')
            ->assertExists(
                $report->file->path
            );

        $content =
            Storage::disk('local')
                ->get(
                    $report->file->path
                );

        $this->assertStringStartsWith(
            "\xEF\xBB\xBF",
            $content
        );
    }

    public function test_excel_and_pdf_writers_generate_real_file_signatures(): void
    {
        $sample = [
            'period' => [
                'from' => '2026-08-01',
                'to' => '2026-08-14',
            ],
            'wallet' => [
                'balance' => 100000,
            ],
        ];

        $excel = app(
            ExcelReportWriter::class
        )->write(
            'Sample',
            $sample
        );

        $this->assertSame(
            'xls',
            $excel->extension
        );

        $this->assertStringContainsString(
            '<Workbook',
            $excel->content
        );

        $pdf = app(
            PdfReportWriter::class
        )->write(
            'Sample',
            $sample
        );

        $this->assertSame(
            'pdf',
            $pdf->extension
        );

        $this->assertStringStartsWith(
            '%PDF-',
            $pdf->content
        );
    }

    public function test_cleanup_command_deletes_expired_export_but_keeps_generated_report_history(): void
    {
        Storage::fake('local');

        $graph = $this->createBuildingGraph();
        $user = $this->createUser();

        $file = File::query()->create([
            'uuid' =>
                (string) Str::uuid(),
            'uploaded_by' =>
                $user->id,
            'disk' => 'local',
            'visibility' =>
                FileVisibility::Private,
            'path' =>
                'generated-reports/expired.csv',
            'stored_name' =>
                'expired.csv',
            'original_name' =>
                'expired.csv',
            'extension' => 'csv',
            'mime_type' => 'text/csv',
            'size' => 10,
            'checksum' =>
                hash('sha256', 'expired'),
            'category' =>
                'generated_report',
            'is_confidential' => true,
            'scan_status' =>
                FileScanStatus::Clean,
            'scanned_at' => now(),
            'expires_at' =>
                now()->subMinute(),
        ]);

        Storage::disk('local')
            ->put(
                $file->path,
                'expired'
            );

        $report = GeneratedReport::query()->create([
            'report_definition_id' =>
                $this->definition(
                    'building.financial_summary'
                )->id,
            'building_id' =>
                $graph['building']->id,
            'generated_by' =>
                $user->id,
            'file_id' =>
                $file->id,
            'format' =>
                ReportFormat::Csv,
            'status' =>
                ReportStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->artisan(
            'reports:cleanup'
        )->assertExitCode(0);

        Storage::disk('local')
            ->assertMissing(
                'generated-reports/expired.csv'
            );

        $this->assertSoftDeleted(
            'files',
            [
                'id' => $file->id,
            ]
        );

        $this->assertDatabaseHas(
            'generated_reports',
            [
                'id' => $report->id,
                'file_id' => null,
                'status' =>
                    ReportStatus::Completed->value,
            ]
        );
    }

    private function definition(
        string $code
    ): ReportDefinition {
        return ReportDefinition::query()
            ->firstOrCreate(
                [
                    'code' => $code,
                ],
                [
                    'title' =>
                        'Test Report',
                    'module' =>
                        'reports',
                    'configuration' => [
                        'permission' =>
                            'reports.financial.view',
                        'export_formats' => [
                            'pdf',
                            'excel',
                            'csv',
                        ],
                    ],
                    'is_active' => true,
                ]
            );
    }
}
