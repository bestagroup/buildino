<?php

namespace App\Providers;

use App\Events\FacilityReservationApproved;
use App\Events\FacilityReservationCreated;
use App\Events\InvoiceIssued;
use App\Events\PaymentVerified;
use App\Events\SupportTicketAssigned;
use App\Events\SupportTicketResolved;
use App\Listeners\NotifyInvoiceIssued;
use App\Listeners\NotifyPaymentVerified;
use App\Listeners\NotifyReservationApproved;
use App\Listeners\NotifyReservationCreated;
use App\Listeners\NotifySupportTicketAssigned;
use App\Listeners\NotifySupportTicketResolved;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class DomainEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        InvoiceIssued::class => [NotifyInvoiceIssued::class],
        PaymentVerified::class => [NotifyPaymentVerified::class],
        FacilityReservationCreated::class => [NotifyReservationCreated::class],
        FacilityReservationApproved::class => [NotifyReservationApproved::class],
        SupportTicketAssigned::class => [NotifySupportTicketAssigned::class],
        SupportTicketResolved::class => [NotifySupportTicketResolved::class],
    ];
}
