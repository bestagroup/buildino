<?php

namespace App\Services\Web;

use App\Models\BuildingFacility;
use App\Models\FacilityReservation;
use App\Models\GuestVisit;
use App\Models\ServiceRequest;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\InvoiceAccessService;
use App\Services\Security\FacilityAccessService;
use App\Services\Security\GuestVisitAccessService;

/** Presentation context only. Process APIs remain the authority for writes. */
final class ResidentPortalWorkspace
{
    public function __construct(private readonly PortalAccessService $access) {}

    public function context(User $user): array
    {
        $units = $this->access->residentUnits($user);
        $buildingIds = $units->map(fn ($unit) => $unit->floor?->block?->building_id)->filter()->unique();
        $gateway = (string) config('payment_gateways.default', 'generic');

        return [
            'area' => 'resident',
            'units' => $units->map(fn ($unit) => [
                'id' => $unit->id, 'title' => $unit->title ?: 'واحد '.$unit->unit_number,
                'building_id' => $unit->floor?->block?->building_id,
                'building_title' => $unit->floor?->block?->building?->title,
            ])->all(),
            'facilities' => BuildingFacility::whereIn('building_id', $buildingIds)->where('is_active', true)
                ->with(['building:id,title,currency', 'facilityReservationRules'])->orderBy('title')->get()
                ->each(fn ($facility) => $facility->setAttribute('resident_policy', $this->facilityPolicy($facility))),
            'support_categories' => SupportCategory::where('is_active', true)->orderBy('title')->get(),
            'payment_gateway' => ['default' => $gateway, 'enabled' => (bool) config('payment_gateways.gateways.'.$gateway.'.enabled', false)],
        ];
    }

    public function statuses(string $resource): array
    {
        $states = match ($resource) {
            'invoices' => ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled', 'void'],
            'reservations' => ['pending', 'payment_pending', 'approved', 'confirmed', 'completed', 'cancelled', 'rejected', 'expired'],
            'guests' => ['invited', 'entered', 'exited', 'cancelled', 'expired'],
            'services' => ['open', 'assigned', 'in_progress', 'awaiting_confirmation', 'completed', 'cancelled'],
            'support' => ['open', 'assigned', 'in_progress', 'waiting_user', 'resolved', 'closed'],
            default => [],
        };
        $presenter = app(WebDataTablePresenter::class);

        return collect($states)->mapWithKeys(fn ($state) => [$state => $presenter->statusLabel($state)])->all();
    }

    private function facilityPolicy(BuildingFacility $facility): string
    {
        $rule = $facility->facilityReservationRules->first();
        $parts = [$facility->requires_approval ? 'نیازمند تأیید مدیر' : 'بدون تأیید مدیر'];
        if ($rule) {
            if ($rule->cancel_before_minutes > 0) {
                $parts[] = 'مهلت لغو: '.$rule->cancel_before_minutes.' دقیقه پیش از شروع';
            }
            if ($rule->max_advance_days > 0) {
                $parts[] = 'رزرو تا '.$rule->max_advance_days.' روز آینده';
            }
            if ($facility->requires_payment) {
                $currency = $facility->building?->currency === 'IRT' ? 'تومان' : 'ریال';
                $parts[] = 'جریمه لغو: '.number_format((int) $rule->cancellation_fee).' '.$currency;
                $parts[] = 'بازپرداخت مانده پس از جریمه: '.($rule->refund_percentage ?? 100).'٪';
            }
        }

        return strtr(implode(' · ', $parts), array_combine(range(0, 9), ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']));
    }

    /** Called only after PortalOperationDetailService has authorized the record. */
    public function actions(User $user, string $resource, int $id): array
    {
        $record = match ($resource) {
            'invoices' => UnitInvoice::findOrFail($id),
            'reservations' => FacilityReservation::with('buildingFacility.facilityReservationRules')->findOrFail($id),
            'guests' => GuestVisit::findOrFail($id),
            'services' => ServiceRequest::with(['quotes.provider', 'building:id,currency'])->findOrFail($id),
            'support' => SupportTicket::findOrFail($id),
            default => null,
        };
        if (! $record) {
            return [];
        }
        $state = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
        $actions = [];
        if ($resource === 'invoices' && in_array($state, ['issued', 'partial', 'overdue'], true)
            && $record->outstanding_amount > 0 && app(InvoiceAccessService::class)->canPay($user, $record)) {
            $actions[] = ['type' => 'invoice-pay', 'id' => $id, 'number' => $record->invoice_number, 'amount' => (int) $record->outstanding_amount];
        }
        if ($resource === 'reservations') {
            $access = app(FacilityAccessService::class);
            if ($state === 'payment_pending' && $access->canViewReservation($user, $record)) {
                $actions[] = ['type' => 'reservation-pay', 'id' => $id, 'title' => $record->buildingFacility?->title];
            }
            if (in_array($state, ['pending', 'payment_pending', 'approved', 'confirmed'], true) && $access->canCancelReservation($user, $record)) {
                $actions[] = ['type' => 'reservation-cancel', 'id' => $id];
            }
        }
        if ($resource === 'guests' && $state === 'invited' && app(GuestVisitAccessService::class)->allowsForVisit($user, $record, 'update')) {
            $actions[] = ['type' => 'guest-cancel', 'id' => $id];
        }
        if ($resource === 'services' && (int) $record->requested_by === (int) $user->id) {
            if (in_array($state, ['open', 'assigned'], true)) {
                foreach ($record->quotes as $quote) {
                    $quoteState = $quote->status instanceof \BackedEnum ? $quote->status->value : $quote->status;
                    if ($quoteState === 'pending' && (! $quote->valid_until || $quote->valid_until->isFuture())) {
                        $actions[] = ['type' => 'quote-accept', 'id' => $quote->id, 'amount' => $quote->amount, 'currency' => $record->building?->currency === 'IRT' ? 'تومان' : 'ریال', 'title' => trim(($quote->provider?->first_name ?? '').' '.($quote->provider?->last_name ?? ''))];
                    }
                }
            }
            if ($state === 'awaiting_confirmation') {
                $actions[] = ['type' => 'service-confirm', 'id' => $id];
            }
            if (! in_array($state, ['completed', 'cancelled'], true)) {
                $actions[] = ['type' => 'service-cancel', 'id' => $id];
            }
        }
        if ($resource === 'support' && (int) $record->user_id === (int) $user->id) {
            $actions[] = ['type' => 'ticket-conversation', 'id' => $id, 'number' => $record->ticket_number, 'title' => $record->subject, 'state' => $state];
            if (in_array($state, ['resolved', 'closed'], true)) {
                $actions[] = ['type' => 'ticket-reopen', 'id' => $id];
            }
        }

        return $actions;
    }
}
