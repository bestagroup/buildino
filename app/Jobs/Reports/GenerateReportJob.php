<?php

namespace App\Jobs\Reports;

use App\Enums\FileScanStatus;
use App\Enums\FileVisibility;
use App\Enums\ReportStatus;
use App\Models\File;
use App\Models\GeneratedReport;
use App\Services\Reports\Export\ReportExportWriterFactory;
use App\Services\Reports\ReportDataResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $generatedReportId
    ) {
        $this->onQueue(
            config(
                'report_exports.queue',
                'reports'
            )
        );
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function middleware(): array
    {
        $lockSeconds = max(
            $this->timeout + 30,
            (int) config(
                'report_exports.overlap_lock_seconds',
                180
            )
        );

        return [
            (new WithoutOverlapping(
                'generated-report:'.$this->generatedReportId
            ))
                ->releaseAfter(10)
                ->expireAfter($lockSeconds),
        ];
    }

    public function handle(
        ReportDataResolver $resolver,
        ReportExportWriterFactory $writers
    ): void {
        $report = GeneratedReport::query()
            ->with([
                'reportDefinition',
                'building',
                'generatedBy',
                'file',
            ])
            ->find($this->generatedReportId);

        if (! $report) {
            return;
        }

        if (
            $report->status
            === ReportStatus::Completed
            && $report->file
        ) {
            return;
        }

        $report->update([
            'status' =>
                ReportStatus::Processing,
            'started_at' =>
                $report->started_at ?? now(),
            'failed_at' => null,
            'error_message' => null,
        ]);

        $definition =
            $report->reportDefinition;

        if (
            ! $definition
            || ! $definition->is_active
        ) {
            throw new \RuntimeException(
                'Report definition is missing or inactive.'
            );
        }

        $data = $resolver->resolve(
            $definition,
            $report->building,
            $report->filters ?? []
        );

        $writer = $writers->for(
            $report->format
        );

        $payload = $writer->write(
            $definition->title,
            $data
        );

        $disk = config(
            'report_exports.disk',
            'local'
        );

        $directory = trim(
            config(
                'report_exports.directory',
                'generated-reports'
            ),
            '/'
        );

        $storedName =
            sprintf(
                '%s-%d-%s.%s',
                Str::slug(
                    $definition->code
                ),
                $report->getKey(),
                Str::lower(
                    Str::random(12)
                ),
                $payload->extension
            );

        $path = $directory
            .'/'
            .now()->format('Y/m')
            .'/'
            .$storedName;

        if (
            ! Storage::disk($disk)
                ->put(
                    $path,
                    $payload->content
                )
        ) {
            throw new \RuntimeException(
                'Report export file could not be stored.'
            );
        }

        $retentionDays = max(
            1,
            (int) config(
                'report_exports.retention_days',
                7
            )
        );

        try {
            $file = File::query()->create([
                'uuid' =>
                    (string) Str::uuid(),

                'uploaded_by' =>
                    $report->generated_by,

                'disk' => $disk,

                'visibility' =>
                    FileVisibility::Private,

                'path' => $path,

                'stored_name' =>
                    $storedName,

                'original_name' =>
                    $this->originalName(
                        $definition->code,
                        $payload->extension
                    ),

                'extension' =>
                    $payload->extension,

                'mime_type' =>
                    $payload->mimeType,

                'size' =>
                    strlen($payload->content),

                'checksum' =>
                    hash(
                        'sha256',
                        $payload->content
                    ),

                'category' =>
                    'generated_report',

                'is_confidential' =>
                    true,

                /*
                 * This file is generated internally by the server,
                 * so upload malware scanning is not applicable.
                 */
                'scan_status' =>
                    FileScanStatus::Clean,

                'scanned_at' => now(),

                'expires_at' =>
                    now()->addDays(
                        $retentionDays
                    ),

                'metadata' => [
                    'generated_report_id' =>
                        $report->getKey(),

                    'report_definition_code' =>
                        $definition->code,

                    'filters' =>
                        $report->filters ?? [],
                ],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)
                ->delete($path);

            throw $exception;
        }

        $oldFile = $report->file;

        try {
            $report->update([
                'file_id' =>
                    $file->getKey(),

                'status' =>
                    ReportStatus::Completed,

                'completed_at' =>
                    now(),

                'failed_at' =>
                    null,

                'error_message' =>
                    null,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)
                ->delete($path);

            $file->delete();

            throw $exception;
        }

        /*
         * A retry may successfully replace an earlier generated file.
         * Delete the previous physical artifact only after the new one
         * is fully committed to the report record.
         */
        if (
            $oldFile
            && ! $oldFile->is($file)
        ) {
            Storage::disk(
                $oldFile->disk
            )->delete(
                $oldFile->path
            );

            $oldFile->delete();
        }
    }

    public function failed(
        ?\Throwable $exception
    ): void {
        if ($exception) {
            report($exception);
        }

        GeneratedReport::query()
            ->whereKey(
                $this->generatedReportId
            )
            ->where(
                'status',
                '!=',
                ReportStatus::Completed->value
            )
            ->update([
                'status' =>
                    ReportStatus::Failed->value,

                'failed_at' =>
                    now(),

                /*
                 * Do not persist raw exception text. Writer/storage/database
                 * exceptions may contain filesystem paths, SQL details or
                 * provider data. The full exception is kept in protected logs.
                 */
                'error_message' =>
                    $exception
                        ? sprintf(
                            'Report generation failed [%s].',
                            class_basename($exception)
                        )
                        : 'Report generation failed.',
            ]);
    }

    public function tags(): array
    {
        return [
            'report-export',
            'generated-report:'
                .$this->generatedReportId,
        ];
    }

    private function originalName(
        string $code,
        string $extension
    ): string {
        return Str::slug($code)
            .'-'
            .now()->format('Ymd-His')
            .'.'
            .$extension;
    }
}
