<?php

namespace App\Services\Demo;

use App\Enums\BuildingBillType;
use App\Enums\FacilityType;
use App\Enums\FacilityWalletPayerSource;
use App\Enums\InvoiceStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestPayerSource;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\WalletTransferType;
use App\Models\Announcement;
use App\Models\Block;
use App\Models\Building;
use App\Models\BuildingExpense;
use App\Models\BuildingFacility;
use App\Models\ChargePeriod;
use App\Models\Complex;
use App\Models\DocumentRecord;
use App\Models\FacilityReservation;
use App\Models\FacilityReservationRule;
use App\Models\FacilitySchedule;
use App\Models\FacilityTimeSlot;
use App\Models\FinancialCategory;
use App\Models\Floor;
use App\Models\GeneratedReport;
use App\Models\Guest;
use App\Models\GuestVisit;
use App\Models\InvoiceItem;
use App\Models\MeetingMinute;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\ReportDefinition;
use App\Models\ReservationWalletPayment;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\SupportCategory;
use App\Models\SupportMessage;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitChargeSetting;
use App\Models\UnitInvoice;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Models\UserRoleAssignment;
use App\Services\PaymentService;
use App\Services\ServiceMarketplace\ServiceRequestMarketplaceService;
use App\Services\Wallet\BuildingBillPaymentService;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WalletTopUpService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class DemoDataGenerator
{
    private array $settings = [];
    private string $scale = 'medium';
    private string $batch = '';
    private int $userSequence = 0;
    private string $mobileBatch = '700';
    private ?Closure $progress = null;
    private string $passwordHash = '';

    private array $summary = [];
    private array $credentials = [];

    private array $firstNames = [
        'علی', 'رضا', 'مهدی', 'حسین', 'محمد', 'امیر', 'سعید', 'آرمان',
        'نرگس', 'سارا', 'مریم', 'زهرا', 'الهام', 'نگار', 'سمیه', 'پریناز',
    ];

    private array $lastNames = [
        'احمدی', 'محمدی', 'رضایی', 'حسینی', 'کریمی', 'مرادی', 'کاظمی',
        'صادقی', 'جعفری', 'رحیمی', 'اکبری', 'موسوی', 'قاسمی', 'یوسفی',
    ];

    private array $serviceTitles = [
        ['electricity', 'بررسی و رفع مشکل برق واحد'],
        ['plumbing', 'رفع نشتی و مشکل تأسیسات آب'],
        ['elevator', 'بازدید و سرویس آسانسور'],
        ['cleaning', 'درخواست خدمات نظافت'],
        ['hvac', 'سرویس سیستم سرمایش و گرمایش'],
        ['security', 'بررسی تجهیزات امنیتی'],
    ];

    private array $supportSubjects = [
        'مشکل در نمایش صورتحساب',
        'پیگیری پرداخت شارژ',
        'اشکال در رزرو امکانات',
        'درخواست اصلاح اطلاعات واحد',
        'پیگیری ورود مهمان',
        'سؤال درباره خدمات ساختمان',
    ];

    public function __construct(
        private readonly WalletService $wallets,
        private readonly WalletTopUpService $topUps,
        private readonly PaymentService $payments,
        private readonly BuildingBillPaymentService $bills,
        private readonly ServiceRequestMarketplaceService $marketplace
    ) {
    }

    public function generate(
        string $scale = 'medium',
        ?int $seed = null,
        ?string $batch = null,
        ?Closure $progress = null
    ): array {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new InvalidArgumentException(
                'Demo data generation is restricted to local/testing/staging environments.'
            );
        }

        $scales = (array) config('demo_data.scales', []);

        if (! isset($scales[$scale])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown demo scale [%s]. Available scales: %s',
                    $scale,
                    implode(', ', array_keys($scales))
                )
            );
        }

        $this->scale = $scale;
        $this->settings = $scales[$scale];
        $this->progress = $progress;

        $seed ??= (int) now()->format('His');
        mt_srand($seed);

        $this->batch = $batch
            ? Str::upper(Str::slug($batch, '-'))
            : 'D'.now()->format('ymdHis').'-'.Str::upper(Str::random(4));

        $this->mobileBatch = $this->reserveMobileBatch();
        $this->passwordHash = Hash::make(
            (string) config('demo_data.password', 'Demo@1405')
        );

        $this->summary = [
            'complexes' => 0,
            'buildings' => 0,
            'blocks' => 0,
            'floors' => 0,
            'units' => 0,
            'users' => 0,
            'ownerships' => 0,
            'occupancies' => 0,
            'guest_visits' => 0,
            'facilities' => 0,
            'reservations' => 0,
            'invoices' => 0,
            'payments' => 0,
            'wallet_transfers' => 0,
            'services' => 0,
            'settled_services' => 0,
            'support_tickets' => 0,
            'notifications' => 0,
            'documents' => 0,
            'reports' => 0,
        ];

        $this->credentials = [
            'manager' => null,
            'resident' => null,
            'provider' => null,
            'password' => (string) config('demo_data.password', 'Demo@1405'),
        ];

        $actor = $this->systemActor();
        $managerRole = $this->managerRole();
        $providerRole = $this->providerRole();
        $supportCatalog = $this->supportCatalog();
        $reportDefinitions = ReportDefinition::query()
            ->where('is_active', true)
            ->get();

        $this->say(
            sprintf(
                'Generating Buildino demo batch %s (%s scale)...',
                $this->batch,
                $this->scale
            )
        );

        for ($complexIndex = 1; $complexIndex <= $this->settings['complexes']; $complexIndex++) {
            $complex = $this->createComplex($complexIndex);

            for ($buildingIndex = 1; $buildingIndex <= $this->settings['buildings_per_complex']; $buildingIndex++) {
                $this->say(
                    sprintf(
                        '  Building %d/%d in complex %d/%d',
                        $buildingIndex,
                        $this->settings['buildings_per_complex'],
                        $complexIndex,
                        $this->settings['complexes']
                    )
                );

                $building = $this->createBuilding(
                    $complex,
                    $complexIndex,
                    $buildingIndex
                );

                $manager = $this->createUser('manager');
                $this->assignRole($manager, $managerRole, $building, $actor);

                $providers = collect();

                for ($i = 0; $i < $this->settings['providers_per_building']; $i++) {
                    $provider = $this->createUser('provider');
                    $this->assignRole($provider, $providerRole, $building, $actor);
                    $providers->push($provider);
                }

                $units = $this->createStructureAndResidents(
                    $building,
                    $manager,
                    $actor
                );

                $this->createFacilities($building, $manager);
                $this->createFinanceCatalog($building);
                $this->fundUnits($building, $units, $actor);
                $this->createInvoices($building, $units, $actor);
                $this->createBuildingExpensesAndBills($building, $manager, $actor);
                $this->createGuestVisits($building, $units, $manager);
                $this->createReservations($building, $units, $manager, $actor);
                $this->createServices($building, $units, $providers, $actor);
                $this->createSupport($building, $units, $manager, $supportCatalog);
                $this->createNotifications($units);
                $this->createDocumentsAndReports(
                    $building,
                    $manager,
                    $reportDefinitions
                );
                $this->createAnnouncements($building, $manager, $units);
            }
        }

        $result = [
            'batch' => $this->batch,
            'scale' => $this->scale,
            'seed' => $seed,
            'counts' => $this->summary,
            'credentials' => $this->credentials,
        ];

        $this->say('Demo data generation completed.');

        return $result;
    }

    public function estimate(string $scale): array
    {
        $settings = config('demo_data.scales.'.$scale);

        if (! is_array($settings)) {
            throw new InvalidArgumentException('Unknown demo scale: '.$scale);
        }

        $buildings = $settings['complexes'] * $settings['buildings_per_complex'];
        $units = $buildings
            * $settings['blocks_per_building']
            * $settings['floors_per_block']
            * $settings['units_per_floor'];

        return [
            'complexes' => $settings['complexes'],
            'buildings' => $buildings,
            'units' => $units,
            'approx_users' => (int) round($units * 1.7)
                + $buildings * (1 + $settings['providers_per_building']),
            'invoices' => $units * $settings['invoice_months'],
            'reservations' => $buildings * $settings['reservations_per_building'],
            'services' => $buildings * $settings['services_per_building'],
            'tickets' => $buildings * $settings['tickets_per_building'],
            'guest_visits' => $buildings * $settings['guest_visits_per_building'],
        ];
    }

    private function createComplex(int $index): Complex
    {
        $complex = Complex::query()->create([
            'code' => sprintf('%s-C%02d', $this->batch, $index),
            'title' => sprintf('مجتمع آزمایشی %02d', $index),
            'province' => 'تهران',
            'city' => $index % 2 === 0 ? 'تهران' : 'کرج',
            'address' => 'آدرس آزمایشی تولیدشده برای سناریوی داشبورد Buildino',
            'postal_code' => (string) (1400000000 + mt_rand(1000000, 9000000)),
            'sort_order' => $index,
            'is_active' => true,
        ]);

        $this->summary['complexes']++;

        return $complex;
    }

    private function createBuilding(
        Complex $complex,
        int $complexIndex,
        int $buildingIndex
    ): Building {
        $unitCount = $this->settings['blocks_per_building']
            * $this->settings['floors_per_block']
            * $this->settings['units_per_floor'];

        $building = Building::query()->create([
            'complex_id' => $complex->getKey(),
            'code' => sprintf(
                '%s-C%02d-B%02d',
                $this->batch,
                $complexIndex,
                $buildingIndex
            ),
            'title' => sprintf(
                'ساختمان دمو %02d-%02d',
                $complexIndex,
                $buildingIndex
            ),
            'building_number' => (string) $buildingIndex,
            'address' => 'تهران - نشانی نمونه ساختمان جهت تست داشبورد',
            'postal_code' => (string) (1500000000 + mt_rand(1000000, 9000000)),
            'timezone' => 'Asia/Tehran',
            'currency' => 'IRR',
            'floors_count' => $this->settings['blocks_per_building']
                * $this->settings['floors_per_block'],
            'units_count' => $unitCount,
            'parking_count' => $unitCount,
            'storage_count' => $unitCount,
            'construction_year' => mt_rand(1390, 1404),
            'is_active' => true,
        ]);

        DB::table('building_rules')->insert([
            [
                'building_id' => $building->getKey(),
                'title' => 'قوانین استفاده از مشاعات',
                'content' => 'رعایت ساعات سکوت، نظافت و مقررات رزرو امکانات الزامی است.',
                'is_active' => true,
                'effective_from' => now()->subYear()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'building_id' => $building->getKey(),
                'title' => 'قوانین ورود مهمان',
                'content' => 'ثبت مهمان پیش از ورود و ثبت خروج در سامانه الزامی است.',
                'is_active' => true,
                'effective_from' => now()->subYear()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('building_emergency_contacts')->insert([
            [
                'building_id' => $building->getKey(),
                'title' => 'مدیریت ساختمان',
                'phone' => '021'.mt_rand(20000000, 89999999),
                'description' => 'شماره تماس نمونه',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'building_id' => $building->getKey(),
                'title' => 'نگهبانی',
                'phone' => '021'.mt_rand(20000000, 89999999),
                'description' => 'شماره تماس نمونه',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->summary['buildings']++;

        return $building;
    }

    /**
     * @return Collection<int, array{unit: Unit, owner: User, resident: User}>
     */
    private function createStructureAndResidents(
        Building $building,
        User $manager,
        User $actor
    ): Collection {
        $units = collect();
        $parkingRows = [];
        $storageRows = [];

        for ($blockIndex = 1; $blockIndex <= $this->settings['blocks_per_building']; $blockIndex++) {
            $block = Block::query()->create([
                'building_id' => $building->getKey(),
                'title' => 'بلوک '.$this->faBlockName($blockIndex),
                'sort_order' => $blockIndex,
                'is_active' => true,
            ]);

            $this->summary['blocks']++;

            for ($floorIndex = 1; $floorIndex <= $this->settings['floors_per_block']; $floorIndex++) {
                $floor = Floor::query()->create([
                    'block_id' => $block->getKey(),
                    'floor_number' => $floorIndex,
                    'title' => 'طبقه '.$floorIndex,
                    'sort_order' => $floorIndex,
                ]);

                $this->summary['floors']++;

                for ($unitIndex = 1; $unitIndex <= $this->settings['units_per_floor']; $unitIndex++) {
                    $unitNumber = sprintf('%d%02d', $floorIndex, $unitIndex);

                    $unit = Unit::query()->create([
                        'floor_id' => $floor->getKey(),
                        'unit_number' => $unitNumber,
                        'title' => 'واحد '.$unitNumber,
                        'area' => mt_rand(70, 180),
                        'bedrooms' => mt_rand(1, 4),
                        'usage_type' => 'residential',
                        'is_active' => true,
                    ]);

                    $owner = $this->createUser('resident');

                    $separateResident = mt_rand(1, 100) <= 68;
                    $resident = $separateResident
                        ? $this->createUser('resident')
                        : $owner;

                    UnitOwnership::query()->create([
                        'unit_id' => $unit->getKey(),
                        'user_id' => $owner->getKey(),
                        'ownership_percentage' => 100,
                        'starts_at' => now()->subYears(mt_rand(1, 5))->toDateString(),
                        'ends_at' => null,
                        'is_primary' => true,
                        'is_active' => true,
                        'created_by' => $actor->getKey(),
                        'notes' => 'داده آزمایشی Buildino',
                    ]);

                    UnitOccupancy::query()->create([
                        'unit_id' => $unit->getKey(),
                        'user_id' => $resident->getKey(),
                        'occupancy_type' => $resident->is($owner) ? 'owner' : 'tenant',
                        'starts_at' => now()->subMonths(mt_rand(1, 30))->toDateString(),
                        'ends_at' => null,
                        'is_primary' => true,
                        'is_active' => true,
                        'created_by' => $actor->getKey(),
                        'notes' => 'داده آزمایشی Buildino',
                    ]);

                    UnitChargeSetting::query()->create([
                        'unit_id' => $unit->getKey(),
                        'payer_source' => 'unit_wallet',
                        'payer_user_id' => null,
                        'auto_collect' => true,
                        'allow_partial' => true,
                    ]);

                    $parkingId = DB::table('parking_spaces')->insertGetId([
                        'building_id' => $building->getKey(),
                        'parking_number' => 'P-'.$blockIndex.'-'.$unitNumber,
                        'title' => 'پارکینگ واحد '.$unitNumber,
                        'type' => 'private',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $storageId = DB::table('storage_units')->insertGetId([
                        'building_id' => $building->getKey(),
                        'storage_number' => 'S-'.$blockIndex.'-'.$unitNumber,
                        'area' => mt_rand(3, 12),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $parkingRows[] = [
                        'unit_id' => $unit->getKey(),
                        'parking_space_id' => $parkingId,
                        'starts_at' => now()->subYear()->toDateString(),
                        'ends_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $storageRows[] = [
                        'unit_id' => $unit->getKey(),
                        'storage_unit_id' => $storageId,
                        'starts_at' => now()->subYear()->toDateString(),
                        'ends_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $units->push([
                        'unit' => $unit,
                        'owner' => $owner,
                        'resident' => $resident,
                    ]);

                    $this->summary['units']++;
                    $this->summary['ownerships']++;
                    $this->summary['occupancies']++;
                }
            }
        }

        foreach (array_chunk($parkingRows, 500) as $chunk) {
            DB::table('unit_parking_assignments')->insert($chunk);
        }

        foreach (array_chunk($storageRows, 500) as $chunk) {
            DB::table('unit_storage_assignments')->insert($chunk);
        }

        return $units;
    }

    private function createFacilities(
        Building $building,
        User $manager
    ): void {
        $definitions = [
            [FacilityType::Gym, 'باشگاه ورزشی', 12_000_000],
            [FacilityType::Pool, 'استخر', 18_000_000],
            [FacilityType::MeetingHall, 'سالن اجتماعات', 25_000_000],
            [FacilityType::RoofGarden, 'روف گاردن', 15_000_000],
        ];

        foreach ($definitions as $index => [$type, $title, $price]) {
            $facility = BuildingFacility::query()->create([
                'building_id' => $building->getKey(),
                'title' => $title,
                'code' => strtolower($type->value).'-'.$building->getKey(),
                'description' => 'امکان مشاع آزمایشی برای تست رزرو و داشبورد',
                'type' => $type,
                'capacity' => mt_rand(8, 40),
                'default_price' => $price,
                'requires_payment' => true,
                'requires_approval' => $index % 2 === 0,
                'is_active' => true,
            ]);

            FacilityReservationRule::query()->create([
                'building_facility_id' => $facility->getKey(),
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 120,
                'min_advance_minutes' => 60,
                'max_advance_days' => 30,
                'max_reservations_per_day' => 2,
                'max_reservations_per_week' => 4,
                'max_reservations_per_month' => 10,
                'max_reservation_per_unit' => 1,
                'cancel_before_minutes' => 120,
                'cancellation_fee' => 2_000_000,
                'refund_percentage' => 90,
                'allow_guest' => true,
                'auto_confirm' => $index % 2 === 1,
            ]);

            for ($day = 0; $day <= 6; $day++) {
                $schedule = FacilitySchedule::query()->create([
                    'building_facility_id' => $facility->getKey(),
                    'day_of_week' => $day,
                    'start_time' => '08:00:00',
                    'end_time' => '22:00:00',
                    'is_active' => true,
                ]);

                foreach ([
                    ['08:00:00', '10:00:00'],
                    ['18:00:00', '20:00:00'],
                    ['20:00:00', '22:00:00'],
                ] as [$start, $end]) {
                    FacilityTimeSlot::query()->create([
                        'facility_schedule_id' => $schedule->getKey(),
                        'start_time' => $start,
                        'end_time' => $end,
                        'capacity' => 1,
                        'price' => $price,
                        'is_active' => true,
                    ]);
                }
            }

            $this->summary['facilities']++;
        }
    }

    private function createFinanceCatalog(Building $building): void
    {
        foreach ([
            ['شارژ عمومی', 'charge'],
            ['آب و تأسیسات', 'expense'],
            ['برق مشاعات', 'expense'],
            ['نگهداری آسانسور', 'expense'],
            ['درآمد امکانات', 'income'],
        ] as [$title, $type]) {
            FinancialCategory::query()->create([
                'building_id' => $building->getKey(),
                'title' => $title,
                'type' => $type,
                'is_active' => true,
            ]);
        }

        DB::table('building_charge_policies')->insert([
            'building_id' => $building->getKey(),
            'mode' => 'mixed',
            'fixed_monthly_amount' => 25_000_000,
            'auto_collect' => true,
            'allow_partial' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fundUnits(
        Building $building,
        Collection $units,
        User $actor
    ): void {
        foreach ($units as $entry) {
            /** @var Unit $unit */
            $unit = $entry['unit'];
            /** @var User $resident */
            $resident = $entry['resident'];

            /*
             * Keep enough headroom for several invoice months plus random
             * facility/service usage. This prevents synthetic load from
             * failing merely because the generated wallet was underfunded.
             */
            $amount = (
                ($this->settings['invoice_months'] * 100)
                + 350
                + mt_rand(0, 100)
            ) * 1_000_000;

            $topUp = $this->topUps->create(
                $building,
                $resident,
                $unit,
                [
                    'amount' => $amount,
                    'method' => PaymentMethod::Online,
                    'gateway' => 'demo',
                    'idempotency_key' => sprintf(
                        'demo:%s:unit:%d:initial-topup',
                        $this->batch,
                        $unit->getKey()
                    ),
                    'description' => 'Demo initial unit wallet funding',
                ]
            );

            $payment = Payment::query()->findOrFail($topUp->payment_id);
            $this->payments->verify($payment, $actor);

            $this->summary['payments']++;
            $this->summary['wallet_transfers']++;
        }
    }

    private function createInvoices(
        Building $building,
        Collection $units,
        User $actor
    ): void {
        $today = CarbonImmutable::today();
        $buildingWallet = $this->wallets->walletFor($building, $building->currency);

        for ($monthOffset = $this->settings['invoice_months'] - 1; $monthOffset >= 0; $monthOffset--) {
            $month = $today->subMonths($monthOffset);
            $periodStart = $month->startOfMonth();
            $periodEnd = $month->endOfMonth();
            $dueDate = $periodEnd->addDays(10);

            $period = ChargePeriod::query()->create([
                'building_id' => $building->getKey(),
                'title' => 'شارژ '.$month->format('Y-m'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'status' => 'issued',
                'created_by' => $actor->getKey(),
            ]);

            foreach ($units as $entry) {
                /** @var Unit $unit */
                $unit = $entry['unit'];

                $base = mt_rand(25, 55) * 1_000_000;
                $variable = mt_rand(0, 30) * 1_000_000;
                $total = $base + $variable;

                $roll = mt_rand(1, 100);
                $paid = 0;
                $status = InvoiceStatus::Issued;

                if ($roll <= 56) {
                    $paid = $total;
                    $status = InvoiceStatus::Paid;
                } elseif ($roll <= 78) {
                    $paid = (int) floor($total * (mt_rand(35, 75) / 100));
                    $status = InvoiceStatus::Partial;
                } elseif ($dueDate->isPast()) {
                    $status = InvoiceStatus::Overdue;
                }

                $invoice = UnitInvoice::query()->create([
                    'building_id' => $building->getKey(),
                    'unit_id' => $unit->getKey(),
                    'charge_period_id' => $period->getKey(),
                    'invoice_number' => sprintf(
                        'INV-%s-%d-%s',
                        $this->batch,
                        $unit->getKey(),
                        $month->format('Ym')
                    ),
                    'issue_date' => $periodStart->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'subtotal' => $total,
                    'discount_amount' => 0,
                    'penalty_amount' => 0,
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'outstanding_amount' => $total - $paid,
                    'status' => $status,
                    'description' => 'صورتحساب آزمایشی تولیدشده برای تست داشبورد',
                    'created_by' => $actor->getKey(),
                ]);

                InvoiceItem::query()->create([
                    'unit_invoice_id' => $invoice->getKey(),
                    'charge_item_id' => null,
                    'title' => 'شارژ و هزینه‌های مشترک',
                    'description' => 'آیتم آزمایشی صورتحساب',
                    'quantity' => 1,
                    'unit_amount' => $total,
                    'total_amount' => $total,
                    'metadata' => [
                        'demo_batch' => $this->batch,
                    ],
                ]);

                if ($paid > 0) {
                    $unitWallet = $this->wallets->walletFor($unit, $building->currency);

                    $transfer = $this->wallets->transfer(
                        $unitWallet,
                        $buildingWallet,
                        $paid,
                        WalletTransferType::ChargeCollection,
                        sprintf('demo:%s:invoice:%d:collection', $this->batch, $invoice->getKey()),
                        $invoice,
                        $actor,
                        'Demo charge collection'
                    );

                    DB::table('invoice_wallet_settlements')->insert([
                        'unit_invoice_id' => $invoice->getKey(),
                        'wallet_transfer_id' => $transfer->getKey(),
                        'source_wallet_id' => $unitWallet->getKey(),
                        'destination_wallet_id' => $buildingWallet->getKey(),
                        'amount' => $paid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->summary['wallet_transfers']++;
                }

                $this->summary['invoices']++;
            }
        }
    }

    private function createBuildingExpensesAndBills(
        Building $building,
        User $manager,
        User $actor
    ): void {
        $categories = FinancialCategory::query()
            ->where('building_id', $building->getKey())
            ->where('type', 'expense')
            ->get();

        foreach (range(1, 8) as $index) {
            $category = $categories->random();
            $amount = mt_rand(12, 55) * 1_000_000;

            BuildingExpense::query()->create([
                'building_id' => $building->getKey(),
                'fund_id' => null,
                'financial_category_id' => $category->getKey(),
                'title' => $category->title.' - هزینه '.$index,
                'amount' => $amount,
                'expense_date' => now()->subDays(mt_rand(0, 27))->toDateString(),
                'invoice_number' => 'EXP-'.$this->batch.'-'.$building->getKey().'-'.$index,
                'status' => 'posted',
                'description' => 'هزینه آزمایشی ساختمان',
                'created_by' => $manager->getKey(),
                'approved_by' => $actor->getKey(),
                'approved_at' => now()->subDays(mt_rand(0, 5)),
                'posted_at' => now()->subDays(mt_rand(0, 5)),
            ]);
        }

        foreach ([
            BuildingBillType::Electricity,
            BuildingBillType::Water,
            BuildingBillType::Gas,
        ] as $index => $type) {
            $amount = mt_rand(8, 25) * 1_000_000;

            try {
                $bill = $this->bills->request(
                    $building,
                    $manager,
                    $type,
                    $amount,
                    [
                        'bill_identifier' => 'BILL-'.$this->batch.'-'.$building->getKey().'-'.$index,
                        'payment_identifier' => 'PAYID-'.mt_rand(100000, 999999),
                        'provider' => 'demo-provider',
                    ]
                );

                $this->bills->complete(
                    $bill,
                    $actor,
                    'DEMO-REF-'.Str::upper(Str::random(10)),
                    ['demo' => true]
                );

                $this->summary['wallet_transfers']++;
            } catch (Throwable) {
                // A building with unusually low collected balance should not
                // abort the entire demo dataset. Invoices remain useful.
            }
        }
    }

    private function createGuestVisits(
        Building $building,
        Collection $units,
        User $manager
    ): void {
        for ($i = 1; $i <= $this->settings['guest_visits_per_building']; $i++) {
            $entry = $units->random();
            $resident = $entry['resident'];
            $unit = $entry['unit'];

            $guest = Guest::query()->create([
                'first_name' => $this->pick($this->firstNames),
                'last_name' => $this->pick($this->lastNames),
                'mobile' => '0918'.str_pad((string) mt_rand(0, 9_999_999), 7, '0', STR_PAD_LEFT),
                'national_code' => null,
                'vehicle_plate' => mt_rand(10, 99).'الف'.mt_rand(100, 999).'-'.mt_rand(10, 99),
            ]);

            $status = $this->pick(['invited', 'entered', 'exited', 'expired']);
            $expected = now()->subDays(mt_rand(0, 20))->setTime(mt_rand(9, 20), 0);

            $visit = GuestVisit::query()->create([
                'guest_id' => $guest->getKey(),
                'unit_id' => $unit->getKey(),
                'registered_by' => $resident->getKey(),
                'expected_entry_at' => $expected,
                'expected_exit_at' => $expected->copy()->addHours(mt_rand(1, 5)),
                'status' => $status,
                'description' => 'بازدید آزمایشی',
            ]);

            if (in_array($status, ['entered', 'exited'], true)) {
                DB::table('guest_access_logs')->insert([
                    'guest_visit_id' => $visit->getKey(),
                    'action' => 'entry',
                    'occurred_at' => $expected,
                    'gate' => 'Main Gate',
                    'entry_method' => 'manual',
                    'verified_by' => $manager->getKey(),
                    'vehicle_plate' => $guest->vehicle_plate,
                    'notes' => 'ثبت آزمایشی ورود',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($status === 'exited') {
                DB::table('guest_access_logs')->insert([
                    'guest_visit_id' => $visit->getKey(),
                    'action' => 'exit',
                    'occurred_at' => $expected->copy()->addHours(mt_rand(1, 5)),
                    'gate' => 'Main Gate',
                    'entry_method' => 'manual',
                    'verified_by' => $manager->getKey(),
                    'vehicle_plate' => $guest->vehicle_plate,
                    'notes' => 'ثبت آزمایشی خروج',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->summary['guest_visits']++;
        }
    }

    private function createReservations(
        Building $building,
        Collection $units,
        User $manager,
        User $actor
    ): void {
        $facilities = BuildingFacility::query()
            ->where('building_id', $building->getKey())
            ->with('facilitySchedules.facilityTimeSlots')
            ->get();

        $buildingWallet = $this->wallets->walletFor($building, $building->currency);

        for ($i = 1; $i <= $this->settings['reservations_per_building']; $i++) {
            $entry = $units->random();
            $unit = $entry['unit'];
            $resident = $entry['resident'];
            $facility = $facilities->random();
            $schedule = $facility->facilitySchedules->random();
            $slot = $schedule->facilityTimeSlots->random();

            $status = $this->weighted([
                ReservationStatus::Completed->value => 35,
                ReservationStatus::Confirmed->value => 22,
                ReservationStatus::Approved->value => 15,
                ReservationStatus::Pending->value => 12,
                ReservationStatus::Cancelled->value => 10,
                ReservationStatus::PaymentPending->value => 6,
            ]);

            $price = (int) $slot->price;
            $date = CarbonImmutable::today()
                ->startOfMonth()
                ->addDays(mt_rand(0, max(0, CarbonImmutable::today()->day - 1)));

            $reservation = FacilityReservation::query()->create([
                'uuid' => (string) Str::uuid(),
                'building_facility_id' => $facility->getKey(),
                'facility_time_slot_id' => $slot->getKey(),
                'unit_id' => $unit->getKey(),
                'user_id' => $resident->getKey(),
                'reservation_date' => $date->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'price' => $price,
                'discount_amount' => 0,
                'final_amount' => $price,
                'rule_snapshot' => ['demo_batch' => $this->batch],
                'status' => $status,
                'approval_type' => ReservationApprovalType::Automatic,
                'description' => 'رزرو آزمایشی',
                'approved_by' => in_array($status, ['approved', 'confirmed', 'completed'], true)
                    ? $manager->getKey()
                    : null,
                'approved_at' => in_array($status, ['approved', 'confirmed', 'completed'], true)
                    ? now()->subDays(mt_rand(0, 10))
                    : null,
                'confirmed_at' => in_array($status, ['confirmed', 'completed'], true)
                    ? now()->subDays(mt_rand(0, 8))
                    : null,
                'expires_at' => null,
            ]);

            if (in_array($status, ['approved', 'confirmed', 'completed'], true)) {
                $unitWallet = $this->wallets->walletFor($unit, $building->currency);

                try {
                    $transfer = $this->wallets->transfer(
                        $unitWallet,
                        $buildingWallet,
                        $price,
                        WalletTransferType::FacilityFee,
                        sprintf('demo:%s:reservation:%d:payment', $this->batch, $reservation->getKey()),
                        $reservation,
                        $actor,
                        'Demo facility reservation fee'
                    );

                    ReservationWalletPayment::query()->create([
                        'facility_reservation_id' => $reservation->getKey(),
                        'wallet_transfer_id' => $transfer->getKey(),
                        'source_wallet_id' => $unitWallet->getKey(),
                        'building_wallet_id' => $buildingWallet->getKey(),
                        'payer_source' => FacilityWalletPayerSource::UnitWallet,
                        'amount' => $price,
                        'status' => 'paid',
                        'paid_at' => now()->subDays(mt_rand(0, 10)),
                    ]);

                    $this->summary['wallet_transfers']++;
                } catch (Throwable) {
                    $reservation->update([
                        'status' => ReservationStatus::PaymentPending,
                        'approved_by' => null,
                        'approved_at' => null,
                        'confirmed_at' => null,
                    ]);
                }
            }

            $this->summary['reservations']++;
        }
    }

    private function createServices(
        Building $building,
        Collection $units,
        Collection $providers,
        User $actor
    ): void {
        for ($i = 1; $i <= $this->settings['services_per_building']; $i++) {
            $entry = $units->random();
            $unit = $entry['unit'];
            $resident = $entry['resident'];
            $provider = $providers->random();
            [$type, $title] = $this->pick($this->serviceTitles);

            $status = $this->weighted([
                ServiceRequestStatus::Open->value => 18,
                ServiceRequestStatus::Assigned->value => 18,
                ServiceRequestStatus::InProgress->value => 16,
                ServiceRequestStatus::AwaitingConfirmation->value => 10,
                ServiceRequestStatus::Completed->value => 32,
                ServiceRequestStatus::Cancelled->value => 6,
            ]);

            $request = ServiceRequest::query()->create([
                'request_number' => sprintf('SR-%s-%d-%04d', $this->batch, $building->getKey(), $i),
                'building_id' => $building->getKey(),
                'unit_id' => $unit->getKey(),
                'requested_by' => $resident->getKey(),
                'type' => $type,
                'priority' => $this->pick(ServiceRequestPriority::values()),
                'status' => $status === ServiceRequestStatus::Completed->value
                    ? ServiceRequestStatus::Assigned
                    : $status,
                'title' => $title,
                'description' => 'درخواست خدمت آزمایشی جهت تست عملکرد سامانه.',
                'assigned_to' => $status === ServiceRequestStatus::Open->value
                    ? null
                    : $provider->getKey(),
                'assigned_at' => $status === ServiceRequestStatus::Open->value
                    ? null
                    : now()->subDays(mt_rand(0, 20)),
                'completed_at' => null,
            ]);

            if ($status === ServiceRequestStatus::Completed->value) {
                try {
                    $quote = $this->marketplace->createQuote(
                        $request,
                        mt_rand(15, 65) * 1_000_000,
                        'پیشنهاد قیمت آزمایشی',
                        now()->addDays(5)->toISOString()
                    );

                    $this->marketplace->acceptQuote(
                        $quote,
                        $resident,
                        ServiceRequestPayerSource::UnitWallet
                    );

                    $this->marketplace->start($request);
                    $this->marketplace->finish($request);
                    $this->marketplace->confirmCompletion($request, $actor);

                    $this->summary['settled_services']++;
                    $this->summary['wallet_transfers'] += 2;
                } catch (Throwable) {
                    $request->update([
                        'status' => ServiceRequestStatus::Completed,
                        'completed_at' => now(),
                    ]);
                }
            } elseif ($status === ServiceRequestStatus::Cancelled->value) {
                $request->update([
                    'status' => ServiceRequestStatus::Cancelled,
                ]);
            }

            $this->summary['services']++;
        }
    }

    private function createSupport(
        Building $building,
        Collection $units,
        User $manager,
        Collection $categories
    ): void {
        for ($i = 1; $i <= $this->settings['tickets_per_building']; $i++) {
            $entry = $units->random();
            $resident = $entry['resident'];
            $unit = $entry['unit'];
            $category = $categories->random();
            $priority = $this->pick(SupportPriority::values());
            $status = $this->weighted([
                SupportTicketStatus::Open->value => 14,
                SupportTicketStatus::Assigned->value => 15,
                SupportTicketStatus::InProgress->value => 20,
                SupportTicketStatus::WaitingUser->value => 10,
                SupportTicketStatus::Resolved->value => 24,
                SupportTicketStatus::Closed->value => 17,
            ]);

            $resolved = in_array($status, ['resolved', 'closed'], true);
            $firstResponded = $status !== 'open';

            $ticket = SupportTicket::query()->create([
                'user_id' => $resident->getKey(),
                'building_id' => $building->getKey(),
                'unit_id' => $unit->getKey(),
                'support_category_id' => $category->getKey(),
                'ticket_number' => sprintf('TKT-%s-%d-%04d', $this->batch, $building->getKey(), $i),
                'subject' => $this->pick($this->supportSubjects),
                'description' => 'شرح آزمایشی درخواست پشتیبانی برای تست Workflow و داشبورد.',
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $status === 'open' ? null : $manager->getKey(),
                'assigned_at' => $status === 'open' ? null : now()->subHours(mt_rand(1, 48)),
                'first_response_at' => $firstResponded ? now()->subHours(mt_rand(1, 24)) : null,
                'response_due_at' => $firstResponded ? now()->addHours(2) : now()->addHours(6),
                'resolution_due_at' => $resolved ? now()->subHour() : now()->addHours(24),
                'resolved_at' => $resolved ? now()->subHours(mt_rand(1, 10)) : null,
                'closed_at' => $status === 'closed' ? now()->subHour() : null,
                'resolution' => $resolved ? 'موضوع بررسی و در داده آزمایشی حل شد.' : null,
            ]);

            SupportMessage::query()->create([
                'support_ticket_id' => $ticket->getKey(),
                'user_id' => $resident->getKey(),
                'message' => 'سلام، لطفاً این مورد را بررسی کنید.',
                'is_internal' => false,
            ]);

            if ($status !== 'open') {
                SupportMessage::query()->create([
                    'support_ticket_id' => $ticket->getKey(),
                    'user_id' => $manager->getKey(),
                    'message' => 'درخواست دریافت شد و در حال بررسی است.',
                    'is_internal' => false,
                ]);
            }

            $this->summary['support_tickets']++;
        }
    }

    private function createNotifications(Collection $units): void
    {
        $users = $units
            ->pluck('resident')
            ->unique('id')
            ->values();

        foreach ($users as $index => $user) {
            if ($index % 2 === 0) {
                UserDevice::query()->updateOrCreate(
                    ['device_id' => 'demo-'.$this->batch.'-'.$user->getKey()],
                    [
                        'user_id' => $user->getKey(),
                        'platform' => $index % 4 === 0 ? 'android' : 'ios',
                        'device_name' => 'Demo Device',
                        'push_token' => 'demo-push-token-'.$this->batch.'-'.$user->getKey(),
                        'last_used_at' => now(),
                    ]
                );
            }

            foreach (['invoice_due', 'reservation_reminder', 'support_update'] as $type) {
                UserNotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'notification_type' => $type,
                        'channel' => NotificationChannel::Database->value,
                    ],
                    ['is_enabled' => true]
                );
            }

            for ($i = 1; $i <= $this->settings['notifications_per_resident']; $i++) {
                $read = mt_rand(1, 100) <= 58;

                NotificationLog::query()->create([
                    'idempotency_key' => sprintf(
                        'demo:%s:user:%d:notification:%d',
                        $this->batch,
                        $user->getKey(),
                        $i
                    ),
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->getKey(),
                    'notification_type' => $this->pick([
                        'invoice_due',
                        'reservation_reminder',
                        'support_update',
                    ]),
                    'channel' => NotificationChannel::Database,
                    'provider' => 'database',
                    'provider_message_id' => null,
                    'title' => $this->pick([
                        'یادآوری پرداخت شارژ',
                        'وضعیت جدید رزرو',
                        'پاسخ جدید پشتیبانی',
                    ]),
                    'message' => 'این اعلان به‌صورت آزمایشی برای بررسی Inbox ایجاد شده است.',
                    'status' => NotificationStatus::Delivered,
                    'attempts' => 1,
                    'last_attempt_at' => now(),
                    'sent_at' => now(),
                    'delivered_at' => now(),
                    'read_at' => $read ? now()->subHours(mt_rand(1, 72)) : null,
                    'response' => ['demo' => true],
                ]);

                $this->summary['notifications']++;
            }
        }
    }

    private function createDocumentsAndReports(
        Building $building,
        User $manager,
        Collection $reportDefinitions
    ): void {
        foreach (range(1, 8) as $index) {
            DocumentRecord::query()->create([
                'documentable_type' => $building->getMorphClass(),
                'documentable_id' => $building->getKey(),
                'title' => 'سند آزمایشی ساختمان '.$index,
                'document_type' => $this->pick([
                    'building',
                    'contract',
                    'financial',
                    'other',
                ]),
                'document_number' => 'DOC-'.$this->batch.'-'.$building->getKey().'-'.$index,
                'document_date' => now()->subDays(mt_rand(1, 300))->toDateString(),
                'expires_at' => null,
                'description' => 'رکورد سند آزمایشی',
                'created_by' => $manager->getKey(),
            ]);

            $this->summary['documents']++;
        }

        foreach (range(1, 4) as $index) {
            MeetingMinute::query()->create([
                'building_id' => $building->getKey(),
                'title' => 'صورتجلسه هیئت‌مدیره ساختمان '.$index,
                'meeting_at' => now()->subDays($index * 20),
                'meeting_number' => 'MM-'.$this->batch.'-'.$building->getKey().'-'.$index,
                'content' => 'محتوای آزمایشی صورتجلسه برای نمایش در سامانه.',
                'created_by' => $manager->getKey(),
            ]);

            $this->summary['documents']++;
        }

        if ($reportDefinitions->isNotEmpty()) {
            foreach (range(1, min(8, $reportDefinitions->count())) as $index) {
                $definition = $reportDefinitions->random();

                GeneratedReport::query()->create([
                    'report_definition_id' => $definition->getKey(),
                    'building_id' => $building->getKey(),
                    'generated_by' => $manager->getKey(),
                    'file_id' => null,
                    'format' => $this->pick(ReportFormat::values()),
                    'status' => ReportStatus::Completed,
                    'filters' => [
                        'demo_batch' => $this->batch,
                        'from' => now()->startOfMonth()->toDateString(),
                        'to' => now()->toDateString(),
                    ],
                    'started_at' => now()->subMinutes(2),
                    'completed_at' => now()->subMinute(),
                    'failed_at' => null,
                    'error_message' => null,
                ]);

                $this->summary['reports']++;
            }
        }
    }

    private function createAnnouncements(
        Building $building,
        User $manager,
        Collection $units
    ): void {
        $users = $units
            ->pluck('resident')
            ->unique('id')
            ->values();

        foreach (range(1, 3) as $index) {
            $announcement = Announcement::query()->create([
                'created_by' => $manager->getKey(),
                'title' => 'اطلاعیه آزمایشی '.$index,
                'content' => 'این اطلاعیه برای نمایش داده‌های واقعی‌تر در محیط Demo ایجاد شده است.',
                'type' => 'general',
                'priority' => $index === 1 ? 'high' : 'normal',
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addDays(20),
                'published_at' => now()->subDay(),
                'is_active' => true,
            ]);

            DB::table('announcement_targets')->insert([
                'announcement_id' => $announcement->getKey(),
                'target_type' => $building->getMorphClass(),
                'target_id' => $building->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $receipts = $users
                ->take(min(30, $users->count()))
                ->map(fn (User $user) => [
                    'announcement_id' => $announcement->getKey(),
                    'user_id' => $user->getKey(),
                    'read_at' => mt_rand(1, 100) <= 60 ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($receipts !== []) {
                DB::table('announcement_receipts')->insert($receipts);
            }
        }
    }

    private function systemActor(): User
    {
        $actor = User::query()
            ->where('mobile', '09120000000')
            ->first();

        if ($actor) {
            return $actor;
        }

        return $this->createUser('system');
    }

    private function managerRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'demo-building-manager'],
            [
                'display_name' => 'مدیر آزمایشی ساختمان',
                'description' => 'Role مخصوص داده Demo با Scope ساختمان',
                'is_system' => false,
            ]
        );

        $permissionIds = Permission::query()->pluck('id')->all();

        if ($permissionIds !== []) {
            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }

    private function providerRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'demo-service-provider'],
            [
                'display_name' => 'ارائه‌دهنده خدمت آزمایشی',
                'description' => 'Role نمونه برای Provider',
                'is_system' => false,
            ]
        );

        $permissionIds = Permission::query()
            ->where(function ($query): void {
                $query
                    ->where('name', 'like', 'service%')
                    ->orWhere('name', 'like', 'wallets.%');
            })
            ->pluck('id')
            ->all();

        if ($permissionIds !== []) {
            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        Building $building,
        User $actor
    ): void {
        UserRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->getKey(),
            'starts_at' => now()->subMinute(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => $actor->getKey(),
        ]);
    }

    private function supportCatalog(): Collection
    {
        return collect([
            ['فنی و تأسیسات', 'درخواست‌های فنی ساختمان'],
            ['مالی و شارژ', 'پرسش‌های مالی و صورتحساب'],
            ['رزرو امکانات', 'مشکلات رزرو Facility'],
            ['کاربری سامانه', 'مشکلات عمومی استفاده از سامانه'],
        ])->map(function (array $item): SupportCategory {
            $category = SupportCategory::query()->firstOrCreate(
                ['title' => 'Demo - '.$item[0]],
                [
                    'description' => $item[1],
                    'is_active' => true,
                ]
            );

            foreach (SupportPriority::cases() as $priority) {
                SupportSlaPolicy::query()->firstOrCreate(
                    [
                        'support_category_id' => $category->getKey(),
                        'priority' => $priority->value,
                    ],
                    [
                        'first_response_minutes' => match ($priority) {
                            SupportPriority::Urgent => 30,
                            SupportPriority::High => 60,
                            SupportPriority::Medium => 180,
                            SupportPriority::Low => 360,
                        },
                        'resolution_minutes' => match ($priority) {
                            SupportPriority::Urgent => 240,
                            SupportPriority::High => 480,
                            SupportPriority::Medium => 1440,
                            SupportPriority::Low => 2880,
                        },
                        'is_active' => true,
                    ]
                );
            }

            return $category;
        });
    }

    private function createUser(string $type): User
    {
        $this->userSequence++;

        $firstName = match ($type) {
            'manager' => 'مدیر',
            'provider' => 'خدمات',
            'system' => 'مدیر',
            default => $this->pick($this->firstNames),
        };

        $lastName = match ($type) {
            'manager' => 'ساختمان '.$this->userSequence,
            'provider' => 'فنی '.$this->userSequence,
            'system' => 'سیستم دمو',
            default => $this->pick($this->lastNames),
        };

        $mobile = '09'.$this->mobileBatch.str_pad(
            (string) $this->userSequence,
            6,
            '0',
            STR_PAD_LEFT
        );

        $email = sprintf(
            'demo.%s.%06d@buildino.local',
            strtolower($this->batch),
            $this->userSequence
        );

        $user = User::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'national_code' => '7'.$this->mobileBatch.str_pad(
                (string) $this->userSequence,
                6,
                '0',
                STR_PAD_LEFT
            ),
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => $this->passwordHash,
            'is_active' => true,
            'is_blocked' => false,
        ]);

        if ($this->credentials['manager'] === null && $type === 'manager') {
            $this->credentials['manager'] = $mobile;
        }

        if ($this->credentials['resident'] === null && $type === 'resident') {
            $this->credentials['resident'] = $mobile;
        }

        if ($this->credentials['provider'] === null && $type === 'provider') {
            $this->credentials['provider'] = $mobile;
        }

        $this->summary['users']++;

        return $user;
    }

    private function reserveMobileBatch(): string
    {
        for ($attempt = 0; $attempt < 900; $attempt++) {
            $candidate = (string) mt_rand(100, 999);

            $exists = User::query()
                ->where('mobile', 'like', '09'.$candidate.'%')
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Unable to reserve unique demo mobile range.');
    }

    private function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        $cursor = 0;

        foreach ($weights as $value => $weight) {
            $cursor += $weight;

            if ($roll <= $cursor) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }

    private function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }

    private function faBlockName(int $index): string
    {
        return ['A', 'B', 'C', 'D', 'E', 'F'][$index - 1] ?? (string) $index;
    }

    private function say(string $message): void
    {
        ($this->progress)?->__invoke($message);
    }
}
