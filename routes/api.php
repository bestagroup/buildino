<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\BuildingExpenseController;
use App\Http\Controllers\Api\V1\BuildingFacilityController;
use App\Http\Controllers\Api\V1\BuildingIncomeController;
use App\Http\Controllers\Api\V1\ComplexController;
use App\Http\Controllers\Api\V1\DocumentRecordController;
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

        Route::apiResources([
            'facilities' => BuildingFacilityController::class,
            'expenses' => BuildingExpenseController::class,
            'incomes' => BuildingIncomeController::class,
            'announcements' => AnnouncementController::class,
            'service-requests' => ServiceRequestController::class,
            'documents' => DocumentRecordController::class,
            'meeting-minutes' => MeetingMinuteController::class,
            'support-tickets' => SupportTicketController::class,
            'payments' => PaymentController::class,
            'invoices' => UnitInvoiceController::class,
        ]);

        Route::get(
            'facility-reservations',
            [FacilityReservationController::class, 'index']
        );

        Route::post(
            'facility-reservations',
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
            'payments/{payment}/verify',
            [PaymentOperationController::class, 'verify']
        )->middleware('throttle:payments');

        Route::post(
            'invoices/{unitInvoice}/issue',
            [InvoiceOperationController::class, 'issue']
        );

        Route::post(
            'support-tickets/{supportTicket}/assign',
            [SupportTicketOperationController::class, 'assign']
        );

        Route::post(
            'support-tickets/{supportTicket}/resolve',
            [SupportTicketOperationController::class, 'resolve']
        );
    });
