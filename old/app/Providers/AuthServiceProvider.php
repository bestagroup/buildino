<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Complex::class => \App\Policies\ComplexPolicy::class,
        \App\Models\Building::class => \App\Policies\BuildingPolicy::class,
        \App\Models\Block::class => \App\Policies\BlockPolicy::class,
        \App\Models\Floor::class => \App\Policies\FloorPolicy::class,
        \App\Models\Unit::class => \App\Policies\UnitPolicy::class,
        \App\Models\ParkingSpace::class => \App\Policies\ParkingSpacePolicy::class,
        \App\Models\StorageUnit::class => \App\Policies\StorageUnitPolicy::class,
        \App\Models\UnitOwnership::class => \App\Policies\UnitOwnershipPolicy::class,
        \App\Models\UnitOccupancy::class => \App\Policies\UnitOccupancyPolicy::class,
        \App\Models\UnitInvitation::class => \App\Policies\UnitInvitationPolicy::class,
        \App\Models\Guest::class => \App\Policies\GuestPolicy::class,
        \App\Models\GuestVisit::class => \App\Policies\GuestVisitPolicy::class,
        \App\Models\Plan::class => \App\Policies\PlanPolicy::class,
        \App\Models\BuildingSubscription::class => \App\Policies\BuildingSubscriptionPolicy::class,
        \App\Models\BuildingFacility::class => \App\Policies\BuildingFacilityPolicy::class,
        \App\Models\FacilityReservation::class => \App\Policies\FacilityReservationPolicy::class,
        \App\Models\FinancialCategory::class => \App\Policies\FinancialCategoryPolicy::class,
        \App\Models\FinancialAccount::class => \App\Policies\FinancialAccountPolicy::class,
        \App\Models\Fund::class => \App\Policies\FundPolicy::class,
        \App\Models\ChargeFormula::class => \App\Policies\ChargeFormulaPolicy::class,
        \App\Models\ChargePeriod::class => \App\Policies\ChargePeriodPolicy::class,
        \App\Models\UnitInvoice::class => \App\Policies\UnitInvoicePolicy::class,
        \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
        \App\Models\BuildingExpense::class => \App\Policies\BuildingExpensePolicy::class,
        \App\Models\BuildingIncome::class => \App\Policies\BuildingIncomePolicy::class,
        \App\Models\LoyaltyReward::class => \App\Policies\LoyaltyRewardPolicy::class,
        \App\Models\SupportTicket::class => \App\Policies\SupportTicketPolicy::class,
        \App\Models\Announcement::class => \App\Policies\AnnouncementPolicy::class,
        \App\Models\SystemSetting::class => \App\Policies\SystemSettingPolicy::class,
        \App\Models\File::class => \App\Policies\FilePolicy::class,
        \App\Models\ReportDefinition::class => \App\Policies\ReportDefinitionPolicy::class,
        \App\Models\GeneratedReport::class => \App\Policies\GeneratedReportPolicy::class,
        \App\Models\DocumentRecord::class => \App\Policies\DocumentRecordPolicy::class,
        \App\Models\MeetingMinute::class => \App\Policies\MeetingMinutePolicy::class,
        \App\Models\ServiceRequest::class => \App\Policies\ServiceRequestPolicy::class,
        \App\Models\AccountingPeriod::class => \App\Policies\AccountingPeriodPolicy::class,
        \App\Models\FinancialReconciliation::class => \App\Policies\FinancialReconciliationPolicy::class,
    ];

    public function boot(): void
    {
        //
    }
}
