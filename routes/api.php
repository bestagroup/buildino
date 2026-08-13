<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\BuildingExpenseController;
use App\Http\Controllers\Api\V1\BuildingFacilityController;
use App\Http\Controllers\Api\V1\BuildingIncomeController;
use App\Http\Controllers\Api\V1\ComplexController;
use App\Http\Controllers\Api\V1\ChargeFormulaController;
use App\Http\Controllers\Api\V1\ChargePeriodController;
use App\Http\Controllers\Api\V1\FinancialTransactionController;
use App\Http\Controllers\Api\V1\DocumentRecordController;
use App\Http\Controllers\Api\V1\FacilityConfigurationController;
use App\Http\Controllers\Api\V1\FacilityReservationController;
use App\Http\Controllers\Api\V1\FloorController;
use App\Http\Controllers\Api\V1\GuestVisitController;
use App\Http\Controllers\Api\V1\InvoiceOperationController;
use App\Http\Controllers\Api\V1\MeetingMinuteController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentOperationController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\SupportTicketOperationController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitInvitationController;
use App\Http\Controllers\Api\V1\UnitInvoiceController;
use App\Http\Controllers\Api\V1\UnitOccupancyController;
use App\Http\Controllers\Api\V1\UnitOwnershipController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth_v1.php';

Route::prefix('v1')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {

        Route::apiResource(
            'complexes',
            ComplexController::class
        );

        Route::get(
            'buildings',
            [BuildingController::class, 'index']
        );

        Route::post(
            'buildings',
            [BuildingController::class, 'store']
        );

        Route::middleware([
            'building.context',
            'building.access',
        ])->group(function (): void {
            Route::get(
                'buildings/{building}',
                [BuildingController::class, 'show']
            );

            Route::match(
                ['put', 'patch'],
                'buildings/{building}',
                [BuildingController::class, 'update']
            );

            Route::delete(
                'buildings/{building}',
                [BuildingController::class, 'destroy']
            );

            Route::get(
                'buildings/{building}/blocks',
                [BlockController::class, 'index']
            );

            Route::post(
                'buildings/{building}/blocks',
                [BlockController::class, 'store']
            );
        });

        Route::get(
            'blocks/{block}',
            [BlockController::class, 'show']
        );

        Route::match(
            ['put', 'patch'],
            'blocks/{block}',
            [BlockController::class, 'update']
        );

        Route::delete(
            'blocks/{block}',
            [BlockController::class, 'destroy']
        );

        Route::get(
            'blocks/{block}/floors',
            [FloorController::class, 'index']
        );

        Route::post(
            'blocks/{block}/floors',
            [FloorController::class, 'store']
        );

        Route::get(
            'floors/{floor}',
            [FloorController::class, 'show']
        );

        Route::match(
            ['put', 'patch'],
            'floors/{floor}',
            [FloorController::class, 'update']
        );

        Route::delete(
            'floors/{floor}',
            [FloorController::class, 'destroy']
        );

        Route::get(
            'floors/{floor}/units',
            [UnitController::class, 'index']
        );

        Route::post(
            'floors/{floor}/units',
            [UnitController::class, 'store']
        );

        Route::get(
            'units/{unit}',
            [UnitController::class, 'show']
        );

        Route::match(
            ['put', 'patch'],
            'units/{unit}',
            [UnitController::class, 'update']
        );

        Route::delete(
            'units/{unit}',
            [UnitController::class, 'destroy']
        );

        /*
        |--------------------------------------------------------------------------
        | Unit Ownerships
        |--------------------------------------------------------------------------
        */

        Route::get(
            'units/{unit}/ownerships',
            [UnitOwnershipController::class, 'index']
        );

        Route::post(
            'units/{unit}/ownerships',
            [UnitOwnershipController::class, 'store']
        );

        Route::get(
            'unit-ownerships/{unitOwnership}',
            [UnitOwnershipController::class, 'show']
        );

        Route::patch(
            'unit-ownerships/{unitOwnership}',
            [UnitOwnershipController::class, 'update']
        );

        Route::post(
            'unit-ownerships/{unitOwnership}/end',
            [UnitOwnershipController::class, 'end']
        );

        /*
        |--------------------------------------------------------------------------
        | Unit Occupancies
        |--------------------------------------------------------------------------
        */

        Route::get(
            'units/{unit}/occupancies',
            [UnitOccupancyController::class, 'index']
        );

        Route::post(
            'units/{unit}/occupancies',
            [UnitOccupancyController::class, 'store']
        );

        Route::get(
            'unit-occupancies/{unitOccupancy}',
            [UnitOccupancyController::class, 'show']
        );

        Route::patch(
            'unit-occupancies/{unitOccupancy}',
            [UnitOccupancyController::class, 'update']
        );

        Route::post(
            'unit-occupancies/{unitOccupancy}/end',
            [UnitOccupancyController::class, 'end']
        );

        /*
        |--------------------------------------------------------------------------
        | Unit Invitations
        |--------------------------------------------------------------------------
        */

        Route::get(
            'units/{unit}/invitations',
            [UnitInvitationController::class, 'index']
        );

        Route::post(
            'units/{unit}/invitations',
            [UnitInvitationController::class, 'store']
        );

        Route::get(
            'unit-invitations/{unitInvitation}',
            [UnitInvitationController::class, 'show']
        );

        Route::post(
            'unit-invitations/{unitInvitation}/resend',
            [UnitInvitationController::class, 'resend']
        );

        Route::post(
            'unit-invitations/{unitInvitation}/cancel',
            [UnitInvitationController::class, 'cancel']
        );

        /*
         * Token operations intentionally require authentication.
         * The bearer invitation token plus authenticated identity
         * must both match before an invitation can be accepted.
         */
        Route::post(
            'unit-invitations/resolve',
            [UnitInvitationController::class, 'resolve']
        );

        Route::post(
            'unit-invitations/accept',
            [UnitInvitationController::class, 'accept']
        );


        /*
        |--------------------------------------------------------------------------
        | Guest Visits
        |--------------------------------------------------------------------------
        |
        | Residents/owners can manage visits for their own unit.
        | Physical entry/exit requires explicit guest-visits.update permission.
        |
        */

        Route::get(
            'units/{unit}/guest-visits',
            [GuestVisitController::class, 'index']
        );

        Route::post(
            'units/{unit}/guest-visits',
            [GuestVisitController::class, 'store']
        );

        Route::get(
            'guest-visits/{guestVisit}',
            [GuestVisitController::class, 'show']
        );

        Route::patch(
            'guest-visits/{guestVisit}',
            [GuestVisitController::class, 'update']
        );

        Route::post(
            'guest-visits/{guestVisit}/cancel',
            [GuestVisitController::class, 'cancel']
        );

        Route::get(
            'guest-visits/{guestVisit}/access-logs',
            [GuestVisitController::class, 'accessLogs']
        );

        Route::post(
            'guest-visits/{guestVisit}/entry',
            [GuestVisitController::class, 'entry']
        );

        Route::post(
            'guest-visits/{guestVisit}/exit',
            [GuestVisitController::class, 'exit']
        );


        /*
        |--------------------------------------------------------------------------
        | Building Facilities
        |--------------------------------------------------------------------------
        */

        Route::get(
            'buildings/{building}/facilities',
            [BuildingFacilityController::class, 'index']
        );

        Route::post(
            'buildings/{building}/facilities',
            [BuildingFacilityController::class, 'store']
        );

        Route::get(
            'facilities/{buildingFacility}',
            [BuildingFacilityController::class, 'show']
        );

        Route::patch(
            'facilities/{buildingFacility}',
            [BuildingFacilityController::class, 'update']
        );

        Route::delete(
            'facilities/{buildingFacility}',
            [BuildingFacilityController::class, 'destroy']
        );

        /*
        |--------------------------------------------------------------------------
        | Facility Configuration
        |--------------------------------------------------------------------------
        */

        Route::get(
            'facilities/{buildingFacility}/schedules',
            [FacilityConfigurationController::class, 'schedules']
        );

        Route::post(
            'facilities/{buildingFacility}/schedules',
            [FacilityConfigurationController::class, 'storeSchedule']
        );

        Route::patch(
            'facilities/{buildingFacility}/schedules/{facilitySchedule}',
            [FacilityConfigurationController::class, 'updateSchedule']
        );

        Route::delete(
            'facilities/{buildingFacility}/schedules/{facilitySchedule}',
            [FacilityConfigurationController::class, 'destroySchedule']
        );

        Route::post(
            'facilities/{buildingFacility}/schedules/{facilitySchedule}/time-slots',
            [FacilityConfigurationController::class, 'storeTimeSlot']
        );

        Route::patch(
            'facilities/{buildingFacility}/schedules/{facilitySchedule}/time-slots/{facilityTimeSlot}',
            [FacilityConfigurationController::class, 'updateTimeSlot']
        );

        Route::delete(
            'facilities/{buildingFacility}/schedules/{facilitySchedule}/time-slots/{facilityTimeSlot}',
            [FacilityConfigurationController::class, 'destroyTimeSlot']
        );

        Route::get(
            'facilities/{buildingFacility}/reservation-rule',
            [FacilityConfigurationController::class, 'reservationRule']
        );

        Route::put(
            'facilities/{buildingFacility}/reservation-rule',
            [FacilityConfigurationController::class, 'upsertReservationRule']
        );

        Route::get(
            'facilities/{buildingFacility}/blackouts',
            [FacilityConfigurationController::class, 'blackouts']
        );

        Route::post(
            'facilities/{buildingFacility}/blackouts',
            [FacilityConfigurationController::class, 'storeBlackout']
        );

        Route::delete(
            'facilities/{buildingFacility}/blackouts/{facilityBlackout}',
            [FacilityConfigurationController::class, 'destroyBlackout']
        );

        Route::get(
            'facilities/{buildingFacility}/availability',
            [FacilityConfigurationController::class, 'availability']
        );

        /*
        |--------------------------------------------------------------------------
        | Facility Reservations
        |--------------------------------------------------------------------------
        */

        Route::get(
            'facility-reservations',
            [FacilityReservationController::class, 'index']
        );

        Route::post(
            'facilities/{buildingFacility}/reservations',
            [FacilityReservationController::class, 'store']
        );

        Route::get(
            'facility-reservations/{facilityReservation}',
            [FacilityReservationController::class, 'show']
        );

        Route::post(
            'facility-reservations/{facilityReservation}/approve',
            [FacilityReservationController::class, 'approve']
        );

        Route::post(
            'facility-reservations/{facilityReservation}/reject',
            [FacilityReservationController::class, 'reject']
        );

        Route::post(
            'facility-reservations/{facilityReservation}/cancel',
            [FacilityReservationController::class, 'cancel']
        );


        /*
        |--------------------------------------------------------------------------
        | Charge Formulas & Charge Periods
        |--------------------------------------------------------------------------
        */

        Route::get(
            'buildings/{building}/charge-formulas',
            [ChargeFormulaController::class, 'index']
        );

        Route::post(
            'buildings/{building}/charge-formulas',
            [ChargeFormulaController::class, 'store']
        );

        Route::get(
            'charge-formulas/{chargeFormula}',
            [ChargeFormulaController::class, 'show']
        );

        Route::patch(
            'charge-formulas/{chargeFormula}',
            [ChargeFormulaController::class, 'update']
        );

        Route::get(
            'buildings/{building}/charge-periods',
            [ChargePeriodController::class, 'index']
        );

        Route::post(
            'buildings/{building}/charge-periods',
            [ChargePeriodController::class, 'store']
        );

        Route::get(
            'charge-periods/{chargePeriod}',
            [ChargePeriodController::class, 'show']
        );

        Route::patch(
            'charge-periods/{chargePeriod}',
            [ChargePeriodController::class, 'update']
        );

        Route::post(
            'charge-periods/{chargePeriod}/calculate',
            [ChargePeriodController::class, 'calculate']
        );

        Route::post(
            'charge-periods/{chargePeriod}/issue',
            [ChargePeriodController::class, 'issue']
        );

        /*
        |--------------------------------------------------------------------------
        | Unit Invoices
        |--------------------------------------------------------------------------
        */

        Route::get(
            'buildings/{building}/invoices',
            [UnitInvoiceController::class, 'buildingIndex']
        );

        Route::get(
            'units/{unit}/invoices',
            [UnitInvoiceController::class, 'unitIndex']
        );

        Route::post(
            'units/{unit}/invoices',
            [UnitInvoiceController::class, 'store']
        );

        Route::get(
            'invoices/{unitInvoice}',
            [UnitInvoiceController::class, 'show']
        );

        Route::patch(
            'invoices/{unitInvoice}',
            [UnitInvoiceController::class, 'update']
        );

        Route::delete(
            'invoices/{unitInvoice}',
            [UnitInvoiceController::class, 'destroy']
        );

        Route::post(
            'invoices/{unitInvoice}/issue',
            [InvoiceOperationController::class, 'issue']
        );

        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Route::get(
            'buildings/{building}/payments',
            [PaymentController::class, 'index']
        );

        Route::post(
            'invoices/{unitInvoice}/payments',
            [PaymentController::class, 'store']
        );

        Route::get(
            'payments/{payment}',
            [PaymentController::class, 'show']
        );

        Route::post(
            'payments/{payment}/verify',
            [PaymentOperationController::class, 'verify']
        )->middleware('throttle:payments');

        /*
        |--------------------------------------------------------------------------
        | Immutable Financial Ledger
        |--------------------------------------------------------------------------
        */

        Route::get(
            'buildings/{building}/financial-transactions',
            [FinancialTransactionController::class, 'index']
        );

        Route::post(
            'buildings/{building}/financial-transactions',
            [FinancialTransactionController::class, 'store']
        );

        Route::apiResources([
            'expenses' => BuildingExpenseController::class,
            'incomes' => BuildingIncomeController::class,
            'announcements' => AnnouncementController::class,
            'service-requests' => ServiceRequestController::class,
            'documents' => DocumentRecordController::class,
            'meeting-minutes' => MeetingMinuteController::class,
            'support-tickets' => SupportTicketController::class,
        ]);

        Route::post(
            'support-tickets/{supportTicket}/assign',
            [SupportTicketOperationController::class, 'assign']
        );

        Route::post(
            'support-tickets/{supportTicket}/resolve',
            [SupportTicketOperationController::class, 'resolve']
        );
    });
require __DIR__.'/wallet_operations_v1.php';
