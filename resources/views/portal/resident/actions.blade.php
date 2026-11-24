@if(!empty($residentActions))
<section class="resident-panel resident-detail-actions" aria-label="اقدام‌های این درخواست">
    <h3>اقدام‌ها</h3><div class="portal-row-actions">
    @foreach($residentActions as $action)
        @switch($action['type'])
            @case('invoice-pay')
                <button type="button" class="portal-primary-button" data-pay-invoice data-invoice-id="{{ $action['id'] }}" data-invoice-number="{{ $action['number'] }}" data-outstanding="{{ $action['amount'] }}">تسویه از کیف پول واحد</button>
                @break
            @case('reservation-pay')<button type="button" class="portal-primary-button" data-reservation-pay data-reservation-id="{{ $action['id'] }}" data-reservation-title="{{ $action['title'] }}">پرداخت از کیف پول</button>@break
            @case('reservation-cancel')<button type="button" class="portal-action-button resident-danger" data-reservation-cancel data-reservation-id="{{ $action['id'] }}">لغو رزرو</button>@break
            @case('guest-cancel')<button type="button" class="portal-action-button resident-danger" data-guest-cancel data-guest-visit-id="{{ $action['id'] }}">لغو مراجعه</button>@break
            @case('quote-accept')<button type="button" class="portal-action-button" data-service-quote-accept data-quote-id="{{ $action['id'] }}" data-quote-amount="{{ $action['amount'] }}">پذیرش پیشنهاد {{ $action['title'] }} · {{ number_format($action['amount']) }} {{ $action['currency'] }}</button>@break
            @case('service-confirm')<button type="button" class="portal-primary-button" data-service-confirm data-service-id="{{ $action['id'] }}">تأیید انجام خدمت</button>@break
            @case('service-cancel')<button type="button" class="portal-action-button resident-danger" data-service-cancel data-service-id="{{ $action['id'] }}">لغو درخواست</button>@break
            @case('ticket-conversation')<button type="button" class="portal-primary-button" data-ticket-conversation data-ticket-id="{{ $action['id'] }}" data-ticket-number="{{ $action['number'] }}" data-ticket-subject="{{ $action['title'] }}" data-ticket-status="{{ $action['state'] }}">مشاهده گفتگو و پاسخ</button>@break
            @case('ticket-reopen')<button type="button" class="portal-action-button" data-ticket-reopen data-ticket-id="{{ $action['id'] }}">بازگشایی تیکت</button>@break
        @endswitch
    @endforeach
    </div>
</section>
@endif
