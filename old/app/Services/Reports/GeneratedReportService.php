<?php

namespace App\Services\Reports;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Jobs\Reports\GenerateReportJob;
use App\Models\Building;
use App\Models\GeneratedReport;
use App\Models\ReportDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GeneratedReportService
{
    public function __construct(
        private readonly ReportDataResolver $resolver
    ) {
    }

    public function request(
        ReportDefinition $definition,
        ?Building $building,
        User $user,
        ReportFormat $format,
        array $filters
    ): GeneratedReport {
        if (! $definition->is_active) {
            throw ValidationException::withMessages([
                'report_definition' =>
                    'This report definition is inactive.',
            ]);
        }

        $allowedFormats =
            $definition
                ->configuration['export_formats']
            ?? ReportFormat::values();

        if (
            ! in_array(
                $format->value,
                $allowedFormats,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'format' =>
                    'Requested export format is not enabled for this report.',
            ]);
        }

        $scope = $this->resolver->scope(
            $definition
        );

        if (
            $scope === 'building'
            && ! $building
        ) {
            throw ValidationException::withMessages([
                'building_id' =>
                    'A Building is required for this report.',
            ]);
        }

        if (
            $scope === 'platform'
            && $building !== null
        ) {
            throw ValidationException::withMessages([
                'building_id' =>
                    'Platform reports must not be scoped to a Building.',
            ]);
        }

        return DB::transaction(
            function () use (
                $definition,
                $building,
                $user,
                $format,
                $filters
            ): GeneratedReport {
                $report =
                    GeneratedReport::query()->create([
                        'report_definition_id' =>
                            $definition->getKey(),

                        'building_id' =>
                            $building?->getKey(),

                        'generated_by' =>
                            $user->getKey(),

                        'format' => $format,

                        'status' =>
                            ReportStatus::Pending,

                        'filters' =>
                            $this->normalizeFilters(
                                $filters
                            ),
                    ]);

                DB::afterCommit(
                    static fn () =>
                        GenerateReportJob::dispatch(
                            $report->getKey()
                        )
                );

                return $report->refresh();
            },
            3
        );
    }

    private function normalizeFilters(
        array $filters
    ): array {
        return array_filter(
            [
                'from' =>
                    $filters['from'] ?? null,

                'to' =>
                    $filters['to'] ?? null,

                'as_of' =>
                    $filters['as_of'] ?? null,

                'granularity' =>
                    $filters['granularity']
                        ?? null,

                'currency' =>
                    isset($filters['currency'])
                        ? strtoupper(
                            $filters['currency']
                        )
                        : null,
            ],
            static fn (mixed $value): bool =>
                $value !== null
                && $value !== ''
        );
    }
}
