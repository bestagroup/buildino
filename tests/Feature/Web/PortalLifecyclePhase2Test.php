<?php

namespace Tests\Feature\Web;

use App\Enums\FacilityType;
use App\Enums\GuestVisitStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ReservationApprovalType;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestQuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\Guest;
use App\Models\GuestVisit;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestQuote;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitInvoice;
use App\Models\User;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalLifecyclePhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_resident_portal_exposes_state_aware_lifecycle_actions(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        config([
            'payment_gateways.default' =>
                'fake',

            'payment_gateways.gateways.fake.enabled' =>
                true,
        ]);

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $unit =
            Unit::query()
                ->where(
                    'unit_number',
                    '101'
                )
                ->whereHas(
                    'unitOwnerships',
                    fn ($query) =>
                        $query->where(
                            'user_id',
                            $owner->getKey()
                        )
                )
                ->with(
                    'floor.block.building'
                )
                ->firstOrFail();

        $building =
            $unit
                ->floor
                ->block
                ->building;

        UnitInvoice::query()
            ->create([
                'building_id' =>
                    $building->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'invoice_number' =>
                    'PORTAL-LIFE-INVOICE-001',

                'issue_date' =>
                    now()->toDateString(),

                'due_date' =>
                    now()
                        ->addDays(7)
                        ->toDateString(),

                'subtotal' =>
                    500000,

                'total_amount' =>
                    500000,

                'paid_amount' =>
                    0,

                'outstanding_amount' =>
                    500000,

                'status' =>
                    InvoiceStatus::Issued,

                'created_by' =>
                    $owner->getKey(),
            ]);

        $facility =
            BuildingFacility::query()
                ->create([
                    'building_id' =>
                        $building->getKey(),

                    'title' =>
                        'سالن تست Lifecycle',

                    'code' =>
                        'PORTAL-LIFE-HALL',

                    'type' =>
                        FacilityType::MeetingHall,

                    'default_price' =>
                        100000,

                    'requires_payment' =>
                        true,

                    'requires_approval' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        FacilityReservation::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'building_facility_id' =>
                    $facility->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'user_id' =>
                    $owner->getKey(),

                'reservation_date' =>
                    now()
                        ->addDays(3)
                        ->toDateString(),

                'start_time' =>
                    '18:00:00',

                'end_time' =>
                    '19:00:00',

                'price' =>
                    100000,

                'final_amount' =>
                    100000,

                'status' =>
                    ReservationStatus::PaymentPending,

                'approval_type' =>
                    ReservationApprovalType::Automatic,
            ]);

        $guest =
            Guest::query()
                ->create([
                    'first_name' =>
                        'مهمان',

                    'last_name' =>
                        'آزمایشی',

                    'mobile' =>
                        '09123334455',
                ]);

        GuestVisit::query()
            ->create([
                'guest_id' =>
                    $guest->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'registered_by' =>
                    $owner->getKey(),

                'expected_entry_at' =>
                    now()
                        ->addDay(),

                'status' =>
                    GuestVisitStatus::Invited,
            ]);

        $quoteRequest =
            ServiceRequest::query()
                ->create([
                    'request_number' =>
                        'PORTAL-LIFE-SRV-001',

                    'building_id' =>
                        $building->getKey(),

                    'unit_id' =>
                        $unit->getKey(),

                    'requested_by' =>
                        $owner->getKey(),

                    'type' =>
                        'electrical',

                    'priority' =>
                        ServiceRequestPriority::Normal,

                    'status' =>
                        ServiceRequestStatus::Assigned,

                    'title' =>
                        'خدمت دارای پیشنهاد',

                    'assigned_to' =>
                        $provider->getKey(),

                    'assigned_at' =>
                        now(),
                ]);

        ServiceRequestQuote::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),

                'service_request_id' =>
                    $quoteRequest->getKey(),

                'provider_user_id' =>
                    $provider->getKey(),

                'amount' =>
                    300000,

                'commission_rate_bps' =>
                    500,

                'commission_amount' =>
                    15000,

                'provider_amount' =>
                    285000,

                'status' =>
                    ServiceRequestQuoteStatus::Pending,

                'valid_until' =>
                    now()
                        ->addDay(),
            ]);

        ServiceRequest::query()
            ->create([
                'request_number' =>
                    'PORTAL-LIFE-SRV-002',

                'building_id' =>
                    $building->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'requested_by' =>
                    $owner->getKey(),

                'type' =>
                    'plumbing',

                'priority' =>
                    ServiceRequestPriority::Normal,

                'status' =>
                    ServiceRequestStatus::AwaitingConfirmation,

                'title' =>
                    'خدمت آماده تأیید',

                'assigned_to' =>
                    $provider->getKey(),

                'assigned_at' =>
                    now(),
            ]);

        SupportTicket::query()
            ->create([
                'user_id' =>
                    $owner->getKey(),

                'building_id' =>
                    $building->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'ticket_number' =>
                    'PORTAL-LIFE-TICKET-001',

                'subject' =>
                    'تیکت Lifecycle',

                'description' =>
                    'برای تست گفتگو و بازگشایی',

                'priority' =>
                    SupportPriority::Medium,

                'status' =>
                    SupportTicketStatus::Closed,

                'closed_at' =>
                    now(),
            ]);

        $this->actingAs(
            $owner,
            'web'
        );

        $this->get(
            '/portal/resident'
        )
            ->assertOk()
            ->assertSee(
                'تاریخچه کیف پول'
            )
            ->assertSee(
                'پرداخت آنلاین'
            )
            ->assertSee(
                'پرداخت از کیف پول'
            )
            ->assertSee(
                'لغو رزرو'
            )
            ->assertSee(
                'لغو دعوت'
            )
            ->assertSee(
                'پذیرش پیشنهاد'
            )
            ->assertSee(
                'تأیید پایان خدمت'
            )
            ->assertSee(
                'گفتگو'
            )
            ->assertSee(
                'بازگشایی'
            );
    }

    public function test_provider_portal_exposes_wallet_history_and_waits_for_locked_payment_before_start(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        $requester =
            $this->user(
                'role.owner@buildino.local'
            );

        $unit =
            Unit::query()
                ->where(
                    'unit_number',
                    '101'
                )
                ->with(
                    'floor.block.building'
                )
                ->firstOrFail();

        ServiceRequest::query()
            ->create([
                'request_number' =>
                    'PORTAL-PROVIDER-LIFE-001',

                'building_id' =>
                    $unit
                        ->floor
                        ->block
                        ->building
                        ->getKey(),

                'unit_id' =>
                    $unit->getKey(),

                'requested_by' =>
                    $requester->getKey(),

                'type' =>
                    'electrical',

                'priority' =>
                    ServiceRequestPriority::Normal,

                'status' =>
                    ServiceRequestStatus::Assigned,

                'title' =>
                    'کار بدون پرداخت قفل‌شده',

                'assigned_to' =>
                    $provider->getKey(),

                'assigned_at' =>
                    now(),
            ]);

        $this->actingAs(
            $provider,
            'web'
        );

        $this->get(
            '/portal/provider'
        )
            ->assertOk()
            ->assertSee(
                'گردش کیف پول ارائه‌دهنده'
            )
            ->assertSee(
                'در انتظار پذیرش پیشنهاد و قفل مبلغ'
            )
            ->assertDontSee(
                'شروع کار'
            );
    }

    public function test_lifecycle_api_routes_used_by_portal_are_registered(): void
    {
        $registered =
            collect(
                Route::getRoutes()
            )
                ->flatMap(
                    fn ($route) =>
                        collect(
                            $route->methods()
                        )
                            ->map(
                                fn (
                                    string $method
                                ): string =>
                                    $method
                                    . ' '
                                    . $route->uri()
                            )
                )
                ->values();

        foreach (
            [
                'POST api/v1/invoices/{unitInvoice}/payments',
                'POST api/v1/payments/{payment}/gateway/initiate',
                'POST api/v1/facility-reservations/{facilityReservation}/pay',
                'POST api/v1/facility-reservations/{facilityReservation}/cancel',
                'POST api/v1/guest-visits/{guestVisit}/cancel',
                'POST api/v1/service-request-quotes/{serviceRequestQuote}/accept',
                'POST api/v1/service-requests/{serviceRequest}/confirm',
                'POST api/v1/service-requests/{serviceRequest}/cancel-financial',
                'GET api/v1/support-tickets/{supportTicket}/messages',
                'POST api/v1/support-tickets/{supportTicket}/messages',
                'POST api/v1/support-tickets/{supportTicket}/reopen',
            ]
            as $route
        ) {
            $this->assertTrue(
                $registered->contains(
                    $route
                ),
                "Missing portal lifecycle route: {$route}"
            );
        }
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }
}
