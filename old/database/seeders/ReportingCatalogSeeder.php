<?php

namespace Database\Seeders;

use App\Models\DashboardWidget;
use App\Models\ReportDefinition;
use Illuminate\Database\Seeder;

class ReportingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $reports = [
            [
                'code' => 'building.management_dashboard',
                'title' => 'Building Management Dashboard',
                'module' => 'dashboard',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/dashboard/management',
                    'permission' =>
                        'reports.dashboard.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'building.financial_summary',
                'title' => 'Building Financial Summary',
                'module' => 'financial',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/reports/financial-summary',
                    'permission' =>
                        'reports.financial.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'building.receivables',
                'title' => 'Building Receivables Aging',
                'module' => 'financial',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/reports/receivables',
                    'permission' =>
                        'reports.receivables.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'building.cash_flow',
                'title' => 'Building Wallet Cash Flow',
                'module' => 'financial',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/reports/cash-flow',
                    'permission' =>
                        'reports.financial.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'building.facility_performance',
                'title' => 'Facility Performance',
                'module' => 'facility',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/reports/facilities',
                    'permission' =>
                        'reports.operations.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'building.service_marketplace',
                'title' => 'Service Marketplace Performance',
                'module' => 'services',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/buildings/{building}/reports/services',
                    'permission' =>
                        'reports.operations.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
            [
                'code' => 'platform.summary',
                'title' => 'Platform Management Summary',
                'module' => 'platform',
                'configuration' => [
                    'endpoint' =>
                        '/api/v1/platform/reports/summary',
                    'permission' =>
                        'reports.platform.view',
                    'export_formats' => [
                        'pdf',
                        'excel',
                        'csv',
                    ],
                ],
            ],
        ];

        foreach ($reports as $report) {
            ReportDefinition::query()->updateOrCreate(
                [
                    'code' => $report['code'],
                ],
                [
                    'title' => $report['title'],
                    'module' => $report['module'],
                    'configuration' =>
                        $report['configuration'],
                    'is_active' => true,
                ]
            );
        }

        $widgets = [
            [
                'code' => 'building.wallet_balance',
                'title' => 'Wallet Balance',
                'type' => 'metric',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'kpis.wallet_balance',
                ],
            ],
            [
                'code' => 'building.receivables_outstanding',
                'title' => 'Outstanding Receivables',
                'type' => 'metric',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'kpis.receivables_outstanding',
                ],
            ],
            [
                'code' => 'building.net_cash_flow',
                'title' => 'Net Cash Flow',
                'type' => 'metric',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'kpis.net_cash_flow',
                ],
            ],
            [
                'code' => 'building.receivables_aging',
                'title' => 'Receivables Aging',
                'type' => 'chart',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'receivables_aging',
                ],
            ],
            [
                'code' => 'building.facility_revenue',
                'title' => 'Facility Paid Amount',
                'type' => 'metric',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'kpis.facility_paid_amount',
                ],
            ],
            [
                'code' => 'building.service_gmv',
                'title' => 'Service Marketplace GMV',
                'type' => 'metric',
                'configuration' => [
                    'source' =>
                        'management_dashboard',
                    'path' =>
                        'kpis.service_gmv',
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            DashboardWidget::query()->updateOrCreate(
                [
                    'code' => $widget['code'],
                ],
                [
                    'title' => $widget['title'],
                    'type' => $widget['type'],
                    'configuration' =>
                        $widget['configuration'],
                    'is_active' => true,
                ]
            );
        }
    }
}
