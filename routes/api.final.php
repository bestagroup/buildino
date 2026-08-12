<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\BuildingController;
use App\Http\Controllers\Api\V1\BuildingExpenseController;
use App\Http\Controllers\Api\V1\BuildingFacilityController;
use App\Http\Controllers\Api\V1\BuildingIncomeController;
use App\Http\Controllers\Api\V1\ComplexController;
use App\Http\Controllers\Api\V1\DocumentRecordController;
use App\Http\Controllers\Api\V1\FacilityReservationController;
use App\Http\Controllers\Api\V1\InvoiceOperationController;
use App\Http\Controllers\Api\V1\MeetingMinuteController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentOperationController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\SupportTicketOperationController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitInvoiceController;
use App\Http\Controllers\Api\V1\UnitOccupancyOperationController;
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
        Route::apiResources([
            'complexes' => ComplexController::class,
            'buildings' => BuildingController::class,
            'units' => UnitController::class,
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

        Route::get('facility-reservations', [FacilityReservationController::class, 'index']);
        Route::post('facility-reservations', [FacilityReservationController::class, 'store']);
        Route::get('facility-reservations/{facilityReservation}', [FacilityReservationController::class, 'show']);
        Route::post('facility-reservations/{facilityReservation}/approve', [FacilityReservationController::class, 'approve']);

        Route::post('payments/{payment}/verify', [PaymentOperationController::class, 'verify'])
            ->middleware('throttle:payments');

        Route::post('invoices/{unitInvoice}/issue', [InvoiceOperationController::class, 'issue']);

        Route::post('unit-occupancies', [UnitOccupancyOperationController::class, 'store']);
        Route::post('unit-occupancies/{unitOccupancy}/end', [UnitOccupancyOperationController::class, 'end']);

        Route::post('support-tickets/{supportTicket}/assign', [SupportTicketOperationController::class, 'assign']);
        Route::post('support-tickets/{supportTicket}/resolve', [SupportTicketOperationController::class, 'resolve']);
    });
