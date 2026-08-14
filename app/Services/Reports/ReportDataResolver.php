<?php

namespace App\Services\Reports;

use App\Models\Building;
use App\Models\ReportDefinition;
use Illuminate\Validation\ValidationException;

final class ReportDataResolver
{
    public function __construct(
        private readonly BuildingReportService $buildingReports,
        private readonly PlatformReportService $platformReports
    ) {
    }

    public function scope(
        ReportDefinition $definition
    ): string {
        return str_starts_with(
            $definition->code,
            'platform.'
        )
            ? 'platform'
            : 'building';
    }

    public function resolve(
        ReportDefinition $definition,
        ?Building $building,
        array $filters
    ): array {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if (
            $this->scope($definition) === 'building'
            && ! $building
        ) {
            throw ValidationException::withMessages([
                'building_id' =>
                    'A Building is required for this report.',
            ]);
        }

        return match ($definition->code) {
            'building.financial_summary' =>
                $this->buildingReports
                    ->financialSummary(
                        $building,
                        $from,
                        $to
                    ),

            'building.receivables' =>
                $this->buildingReports
                    ->receivables(
                        $building,
                        $filters['as_of']
                            ?? $to
                    ),

            'building.cash_flow' =>
                $this->buildingReports
                    ->cashFlow(
                        $building,
                        $from,
                        $to,
                        $filters['granularity']
                            ?? 'day'
                    ),

            'building.facility_performance' =>
                $this->buildingReports
                    ->facilities(
                        $building,
                        $from,
                        $to
                    ),

            'building.service_marketplace' =>
                $this->buildingReports
                    ->services(
                        $building,
                        $from,
                        $to
                    ),

            'building.management_dashboard' =>
                $this->buildingReports
                    ->managementDashboard(
                        $building,
                        $from,
                        $to
                    ),

            'platform.summary' =>
                $this->platformReports
                    ->summary(
                        $from,
                        $to,
                        $filters['currency']
                            ?? 'IRR'
                    ),

            default =>
                throw ValidationException::withMessages([
                    'report_definition' =>
                        'This report definition has no export data resolver.',
                ]),
        };
    }
}
