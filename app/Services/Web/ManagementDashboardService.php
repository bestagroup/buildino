<?php

namespace App\Services\Web;

use App\Enums\InvoiceStatus;
use App\Enums\NotificationChannel;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SupportTicketStatus;
use App\Models\Building;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\GeneratedReport;
use App\Models\GuestVisit;
use App\Models\DocumentRecord;
use App\Models\MeetingMinute;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Services\Reports\BuildingReportService;
use App\Services\Reports\PlatformReportService;
use App\Services\System\SystemHealthService;
use App\Support\Authorization\PermissionChecker;
use App\Support\Jalali\JalaliDateFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Throwable;

final class ManagementDashboardService
{
    public function __construct(
        private readonly ManagementDashboardAccessService $access,
        private readonly BuildingReportService $buildingReports,
        private readonly PlatformReportService $platformReports,
        private readonly SystemHealthService $health,
        private readonly PermissionChecker $permissions,
        private readonly JalaliDateFormatter $jalali
    ) {
    }

    public function build(
        User $user,
        Collection $accessibleBuildings,
        ?Building $selectedBuilding,
        ?string $from = null,
        ?string $to = null
    ): array {
        $period = $this->period(
            $from,
            $to
        );

        $platformAccess =
            $this->access
                ->hasPlatformAccess(
                    $user
                );

        $buildingIds =
            $selectedBuilding
                ? collect([
                    $selectedBuilding->getKey(),
                ])
                : $accessibleBuildings
                    ->pluck('id')
                    ->map(
                        fn ($id): int =>
                            (int) $id
                    )
                    ->values();

        $currency =
            strtoupper(
                $selectedBuilding?->currency
                ?: 'IRR'
            );

        $buildingDashboard = null;
        $buildingReportError = null;

        if ($selectedBuilding) {
            try {
                $buildingDashboard =
                    $this->buildingReports
                        ->managementDashboard(
                            $selectedBuilding,
                            $period['from'],
                            $period['to']
                        );
            } catch (Throwable $exception) {
                $buildingReportError =
                    $this->safeMessage(
                        $exception
                    );
            }
        }

        $platformSummary = null;
        $platformReportError = null;

        if ($platformAccess) {
            try {
                $platformSummary =
                    $this->platformReports
                        ->summary(
                            $period['from'],
                            $period['to'],
                            $currency
                        );
            } catch (Throwable $exception) {
                $platformReportError =
                    $this->safeMessage(
                        $exception
                    );
            }
        }

        $counts = $this->counts(
            $user,
            $buildingIds,
            $selectedBuilding,
            $platformAccess
        );

        return [
            'period' => $period,

            'scope' => [
                'platform' =>
                    $platformAccess,
                'selected_building_id' =>
                    $selectedBuilding?->getKey(),
                'selected_building_title' =>
                    $selectedBuilding?->title,
                'currency' =>
                    $currency,
            ],

            'counts' => $counts,

            'building_dashboard' =>
                $buildingDashboard,

            'building_report_error' =>
                $buildingReportError,

            'platform_summary' =>
                $platformSummary,

            'platform_report_error' =>
                $platformReportError,

            'operations' =>
                $this->operations(
                    $buildingIds
                ),

            /*
             * Recent activity tables are loaded through Yajra DataTables
             * endpoints. Keeping them out of the initial HTML request prevents
             * four unnecessary result-set queries on every dashboard render.
             */
            'recent' => [
                'payments' => collect(),
                'reservations' => collect(),
                'services' => collect(),
                'support' => collect(),
            ],

            'health' =>
                $this->healthData(
                    $user
                ),

            'api' =>
                $this->apiStats(),

            'modules' =>
                $this->modules(
                    $counts
                ),

            'generated_at' =>
                now()->toISOString(),
        ];
    }

    private function counts(
        User $user,
        Collection $buildingIds,
        ?Building $selectedBuilding,
        bool $platformAccess
    ): array {
        $unitQuery = Unit::query();

        if ($selectedBuilding) {
            $unitQuery->whereHas(
                'floor.block',
                fn ($query) =>
                    $query->where(
                        'building_id',
                        $selectedBuilding->getKey()
                    )
            );
        } elseif ($buildingIds->isNotEmpty()) {
            $unitQuery->whereHas(
                'floor.block',
                fn ($query) =>
                    $query->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
            );
        } else {
            $unitQuery->whereRaw('1 = 0');
        }

        $ownershipUsers =
            UnitOwnership::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereNull('starts_at')
                        ->orWhereDate(
                            'starts_at',
                            '<=',
                            today()
                        );
                })
                ->where(function ($query): void {
                    $query
                        ->whereNull('ends_at')
                        ->orWhereDate(
                            'ends_at',
                            '>=',
                            today()
                        );
                })
                ->when(
                    $buildingIds->isNotEmpty(),
                    fn ($query) =>
                        $query->whereHas(
                            'unit.floor.block',
                            fn ($builder) =>
                                $builder->whereIn(
                                    'building_id',
                                    $buildingIds->all()
                                )
                        ),
                    fn ($query) =>
                        $query->whereRaw('1 = 0')
                )
                ->pluck('user_id');

        $occupancyUsers =
            UnitOccupancy::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->whereNull('starts_at')
                        ->orWhereDate(
                            'starts_at',
                            '<=',
                            today()
                        );
                })
                ->where(function ($query): void {
                    $query
                        ->whereNull('ends_at')
                        ->orWhereDate(
                            'ends_at',
                            '>=',
                            today()
                        );
                })
                ->when(
                    $buildingIds->isNotEmpty(),
                    fn ($query) =>
                        $query->whereHas(
                            'unit.floor.block',
                            fn ($builder) =>
                                $builder->whereIn(
                                    'building_id',
                                    $buildingIds->all()
                                )
                        ),
                    fn ($query) =>
                        $query->whereRaw('1 = 0')
                )
                ->pluck('user_id');

        $relatedUsers =
            $ownershipUsers
                ->merge(
                    $occupancyUsers
                )
                ->filter()
                ->unique()
                ->count();

        $facilityQuery =
            BuildingFacility::query();

        $reservationQuery =
            FacilityReservation::query();

        $invoiceQuery =
            UnitInvoice::query();

        $paymentQuery =
            Payment::query();

        $serviceQuery =
            ServiceRequest::query();

        $supportQuery =
            SupportTicket::query();

        $reportQuery =
            GeneratedReport::query();

        $guestVisitQuery =
            GuestVisit::query();

        $documentQuery =
            DocumentRecord::query();

        $minuteQuery =
            MeetingMinute::query();

        if ($buildingIds->isEmpty()) {
            foreach ([
                $facilityQuery,
                $reservationQuery,
                $invoiceQuery,
                $paymentQuery,
                $serviceQuery,
                $supportQuery,
                $reportQuery,
                $guestVisitQuery,
                $documentQuery,
                $minuteQuery,
            ] as $query) {
                $query->whereRaw('1 = 0');
            }
        } else {
            $facilityQuery
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                );

            $reservationQuery
                ->whereHas(
                    'buildingFacility',
                    fn ($query) =>
                        $query->whereIn(
                            'building_id',
                            $buildingIds->all()
                        )
                );

            foreach ([
                $invoiceQuery,
                $paymentQuery,
                $serviceQuery,
                $supportQuery,
                $reportQuery,
                $minuteQuery,
            ] as $query) {
                $query->whereIn(
                    'building_id',
                    $buildingIds->all()
                );
            }

            $guestVisitQuery
                ->whereHas(
                    'unit.floor.block',
                    fn ($query) =>
                        $query->whereIn(
                            'building_id',
                            $buildingIds->all()
                        )
                );

            $documentBuildingTypes = array_values(
                array_unique([
                    (new Building())->getMorphClass(),
                    Building::class,
                ])
            );

            $documentUnitTypes = array_values(
                array_unique([
                    (new Unit())->getMorphClass(),
                    Unit::class,
                ])
            );

            $documentUnitIds = Unit::query()
                ->whereHas(
                    'floor.block',
                    fn ($query) =>
                        $query->whereIn(
                            'building_id',
                            $buildingIds->all()
                        )
                )
                ->pluck('id')
                ->all();

            $documentQuery->where(
                function ($query) use (
                    $buildingIds,
                    $documentBuildingTypes,
                    $documentUnitTypes,
                    $documentUnitIds
                ): void {
                    $query->where(
                        function ($builder) use (
                            $buildingIds,
                            $documentBuildingTypes
                        ): void {
                            $builder
                                ->whereIn(
                                    'documentable_type',
                                    $documentBuildingTypes
                                )
                                ->whereIn(
                                    'documentable_id',
                                    $buildingIds->all()
                                );
                        }
                    );

                    if ($documentUnitIds !== []) {
                        $query->orWhere(
                            function ($builder) use (
                                $documentUnitTypes,
                                $documentUnitIds
                            ): void {
                                $builder
                                    ->whereIn(
                                        'documentable_type',
                                        $documentUnitTypes
                                    )
                                    ->whereIn(
                                        'documentable_id',
                                        $documentUnitIds
                                    );
                            }
                        );
                    }
                }
            );
        }

        $userType = $user->getMorphClass();

        return [
            'buildings' =>
                $buildingIds->count(),

            'units' =>
                (clone $unitQuery)->count(),

            'residents' =>
                $relatedUsers,

            'facilities' =>
                (clone $facilityQuery)
                    ->where(
                        'is_active',
                        true
                    )
                    ->count(),

            'guest_visits_active' =>
                (clone $guestVisitQuery)
                    ->whereIn(
                        'status',
                        [
                            'invited',
                            'entered',
                        ]
                    )
                    ->count(),

            'reservations_active' =>
                (clone $reservationQuery)
                    ->whereIn(
                        'status',
                        [
                            ReservationStatus::Pending->value,
                            ReservationStatus::PaymentPending->value,
                            ReservationStatus::Approved->value,
                            ReservationStatus::Confirmed->value,
                        ]
                    )
                    ->count(),

            'invoices_outstanding' =>
                (int) (clone $invoiceQuery)
                    ->where(
                        'outstanding_amount',
                        '>',
                        0
                    )
                    ->whereIn(
                        'status',
                        [
                            InvoiceStatus::Issued->value,
                            InvoiceStatus::Partial->value,
                            InvoiceStatus::Overdue->value,
                        ]
                    )
                    ->sum(
                        'outstanding_amount'
                    ),

            'payments_paid' =>
                (int) (clone $paymentQuery)
                    ->where(
                        'status',
                        PaymentStatus::Paid->value
                    )
                    ->sum('amount'),

            'services_active' =>
                (clone $serviceQuery)
                    ->whereIn(
                        'status',
                        [
                            ServiceRequestStatus::Open->value,
                            ServiceRequestStatus::Assigned->value,
                            ServiceRequestStatus::InProgress->value,
                            ServiceRequestStatus::AwaitingConfirmation->value,
                        ]
                    )
                    ->count(),

            'support_active' =>
                (clone $supportQuery)
                    ->whereIn(
                        'status',
                        [
                            SupportTicketStatus::Open->value,
                            SupportTicketStatus::Assigned->value,
                            SupportTicketStatus::InProgress->value,
                            SupportTicketStatus::WaitingUser->value,
                        ]
                    )
                    ->count(),

            'reports_generated' =>
                (clone $reportQuery)
                    ->count(),

            'documents' =>
                (clone $documentQuery)->count()
                + (clone $minuteQuery)->count(),

            'notifications_unread' =>
                NotificationLog::query()
                    ->where(
                        'notifiable_type',
                        $userType
                    )
                    ->where(
                        'notifiable_id',
                        $user->getKey()
                    )
                    ->where(
                        'channel',
                        NotificationChannel::Database->value
                    )
                    ->whereNull(
                        'read_at'
                    )
                    ->count(),

            'users_total' =>
                $platformAccess
                    ? User::query()
                        ->where(
                            'is_active',
                            true
                        )
                        ->count()
                    : $relatedUsers,
        ];
    }

    private function operations(
        Collection $buildingIds
    ): array {
        if ($buildingIds->isEmpty()) {
            return [
                'reservations' => [],
                'services' => [],
                'support' => [],
                'invoices' => [],
            ];
        }

        $reservations =
            FacilityReservation::query()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->whereHas(
                    'buildingFacility',
                    fn ($query) =>
                        $query->whereIn(
                            'building_id',
                            $buildingIds->all()
                        )
                )
                ->groupBy('status')
                ->pluck(
                    'aggregate',
                    'status'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        $services =
            ServiceRequest::query()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                )
                ->groupBy('status')
                ->pluck(
                    'aggregate',
                    'status'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        $support =
            SupportTicket::query()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                )
                ->groupBy('status')
                ->pluck(
                    'aggregate',
                    'status'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        $invoices =
            UnitInvoice::query()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->whereIn(
                    'building_id',
                    $buildingIds->all()
                )
                ->groupBy('status')
                ->pluck(
                    'aggregate',
                    'status'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        return [
            'reservations' =>
                $reservations,
            'services' =>
                $services,
            'support' =>
                $support,
            'invoices' =>
                $invoices,
        ];
    }

    private function recent(
        Collection $buildingIds
    ): array {
        if ($buildingIds->isEmpty()) {
            return [
                'payments' => collect(),
                'reservations' => collect(),
                'services' => collect(),
                'support' => collect(),
            ];
        }

        return [
            'payments' =>
                Payment::query()
                    ->with([
                        'building:id,title',
                        'payerUser:id,first_name,last_name,mobile',
                    ])
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->latest('id')
                    ->limit(6)
                    ->get(),

            'reservations' =>
                FacilityReservation::query()
                    ->with([
                        'buildingFacility:id,building_id,title',
                        'user:id,first_name,last_name,mobile',
                        'unit:id,unit_number,title',
                    ])
                    ->whereHas(
                        'buildingFacility',
                        fn ($query) =>
                            $query->whereIn(
                                'building_id',
                                $buildingIds->all()
                            )
                    )
                    ->latest('id')
                    ->limit(6)
                    ->get(),

            'services' =>
                ServiceRequest::query()
                    ->with([
                        'building:id,title',
                        'requestedBy:id,first_name,last_name,mobile',
                        'assignedTo:id,first_name,last_name,mobile',
                    ])
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->latest('id')
                    ->limit(6)
                    ->get(),

            'support' =>
                SupportTicket::query()
                    ->with([
                        'building:id,title',
                        'user:id,first_name,last_name,mobile',
                        'assignedTo:id,first_name,last_name,mobile',
                    ])
                    ->whereIn(
                        'building_id',
                        $buildingIds->all()
                    )
                    ->latest('id')
                    ->limit(6)
                    ->get(),
        ];
    }

    private function healthData(
        User $user
    ): array {
        try {
            if (
                $this->permissions
                    ->allows(
                        $user,
                        'system.health.view',
                        null
                    )
            ) {
                return $this->health
                    ->inspect(
                        true
                    );
            }

            return $this->health
                ->publicReadiness();
        } catch (Throwable $exception) {
            return [
                'status' => 'unknown',
                'ready' => false,
                'message' =>
                    $this->safeMessage(
                        $exception
                    ),
            ];
        }
    }

    private function apiStats(): array
    {
        $routes = collect(
            Route::getRoutes()
        )
            ->filter(
                fn (LaravelRoute $route): bool =>
                    str_starts_with(
                        ltrim(
                            $route->uri(),
                            '/'
                        ),
                        'api/v1'
                    )
            );

        $methodContracts =
            $routes->sum(
                fn (LaravelRoute $route): int =>
                    count(
                        array_filter(
                            $route->methods(),
                            fn (string $method): bool =>
                                ! in_array(
                                    strtoupper($method),
                                    [
                                        'HEAD',
                                        'OPTIONS',
                                    ],
                                    true
                                )
                        )
                    )
            );

        $protected =
            $routes->filter(
                function (
                    LaravelRoute $route
                ): bool {
                    return collect(
                        $route
                            ->gatherMiddleware()
                    )
                        ->contains(
                            fn ($item): bool =>
                                str_contains(
                                    (string) $item,
                                    'auth:sanctum'
                                )
                                || str_contains(
                                    (string) $item,
                                    'Illuminate\\Auth\\Middleware\\Authenticate'
                                )
                        );
                }
            )->count();

        return [
            'version' =>
                (string) config(
                    'api_contract_v1.version',
                    '1.0.0'
                ),
            'paths' =>
                $routes
                    ->pluck(
                        'uri'
                    )
                    ->unique()
                    ->count(),
            'contracts' =>
                $methodContracts,
            'protected_paths' =>
                $protected,
        ];
    }

    private function modules(
        array $counts
    ): array {
        return [
            [
                'key' => 'buildings',
                'title' => 'مجتمع و ساختمان',
                'description' =>
                    'مدیریت مجتمع، ساختمان، بلوک، طبقه، واحد، پارکینگ و انباری.',
                'count' =>
                    $counts['buildings'],
                'unit' => 'ساختمان',
                'icon' => 'building',
            ],
            [
                'key' => 'residents',
                'title' => 'مالکین و ساکنین',
                'description' =>
                    'مالکیت، سکونت، دعوت کاربران و ارتباط معتبر با واحد.',
                'count' =>
                    $counts['residents'],
                'unit' => 'کاربر مرتبط',
                'icon' => 'users',
            ],
            [
                'key' => 'guests',
                'title' => 'مهمان و تردد',
                'description' =>
                    'ثبت مهمان، Visit، ورود و خروج و نگهداری سوابق دسترسی.',
                'count' =>
                    $counts[
                        'guest_visits_active'
                    ],
                'unit' => 'بازدید فعال',
                'icon' => 'users',
            ],
            [
                'key' => 'facilities',
                'title' => 'امکانات و رزرو',
                'description' =>
                    'Facility، برنامه زمانی، Slot، Blackout و رزرو امکانات مشاع.',
                'count' =>
                    $counts[
                        'reservations_active'
                    ],
                'unit' => 'رزرو فعال',
                'icon' => 'calendar',
            ],
            [
                'key' => 'finance',
                'title' => 'شارژ و صورتحساب',
                'description' =>
                    'هزینه مشترک، تخصیص شارژ، Invoice، مطالبات و وصول.',
                'count' =>
                    $counts[
                        'invoices_outstanding'
                    ],
                'unit' => 'مانده مطالبات',
                'icon' => 'invoice',
                'money' => true,
            ],
            [
                'key' => 'wallets',
                'title' => 'کیف پول و پرداخت',
                'description' =>
                    'Wallet کاربر/واحد/ساختمان، Top-up، Payment و جریان واقعی وجه.',
                'count' =>
                    $counts[
                        'payments_paid'
                    ],
                'unit' => 'پرداخت موفق',
                'icon' => 'wallet',
                'money' => true,
            ],
            [
                'key' => 'services',
                'title' => 'بازار خدمات',
                'description' =>
                    'درخواست خدمت، Quote، قفل وجه، تأیید و تسویه ارائه‌دهنده.',
                'count' =>
                    $counts[
                        'services_active'
                    ],
                'unit' => 'درخواست فعال',
                'icon' => 'tools',
            ],
            [
                'key' => 'support',
                'title' => 'پشتیبانی و SLA',
                'description' =>
                    'Ticket، پیام، Assignment، SLA، Resolve، Close و Reopen.',
                'count' =>
                    $counts[
                        'support_active'
                    ],
                'unit' => 'تیکت فعال',
                'icon' => 'support',
            ],
            [
                'key' => 'notifications',
                'title' => 'اعلان و اطلاع‌رسانی',
                'description' =>
                    'Inbox، Device، Preference، SMS، Push و Reminder.',
                'count' =>
                    $counts[
                        'notifications_unread'
                    ],
                'unit' => 'خوانده‌نشده',
                'icon' => 'bell',
            ],
            [
                'key' => 'documents',
                'title' => 'اسناد و صورتجلسات',
                'description' =>
                    'اسناد ساختمان، Document Record، فایل‌ها و صورتجلسات مدیریتی.',
                'count' =>
                    $counts['documents'],
                'unit' => 'رکورد',
                'icon' => 'invoice',
            ],
            [
                'key' => 'reports',
                'title' => 'گزارش و داشبورد',
                'description' =>
                    'گزارش مالی، مطالبات، Cash Flow و خروجی CSV/Excel/PDF.',
                'count' =>
                    $counts[
                        'reports_generated'
                    ],
                'unit' => 'گزارش تولیدشده',
                'icon' => 'chart',
            ],
            [
                'key' => 'security',
                'title' => 'امنیت و کنترل',
                'description' =>
                    'Role/Permission، Scope، Audit، Reconciliation، Health و Release Gate.',
                'count' =>
                    $counts[
                        'users_total'
                    ],
                'unit' => 'کاربر فعال',
                'icon' => 'shield',
            ],
        ];
    }

    private function period(
        ?string $from,
        ?string $to
    ): array {
        $today =
            CarbonImmutable::today();

        $fromDate =
            CarbonImmutable::parse(
                $from
                    ?: $today
                        ->startOfMonth()
                        ->toDateString()
            );

        $toDate =
            CarbonImmutable::parse(
                $to
                    ?: $today
                        ->toDateString()
            );

        return [
            'from' =>
                $fromDate->toDateString(),
            'to' =>
                $toDate->toDateString(),
            'from_jalali' =>
                $this->jalali->date(
                    $fromDate
                ),
            'to_jalali' =>
                $this->jalali->date(
                    $toDate
                ),
        ];
    }

    private function safeMessage(
        Throwable $exception
    ): string {
        if (! app()->environment(
            'local',
            'testing'
        )) {
            return 'داده این بخش در حال حاضر قابل دریافت نیست.';
        }

        return mb_substr(
            $exception->getMessage(),
            0,
            500
        );
    }
}
