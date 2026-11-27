@if(($resource ?? '') === 'guests')
{{-- Guest Modal --}}
<div
    class="modal fade"
    id="guestModal"
    tabindex="-1"
    aria-labelledby="guestModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="guest"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="guestModalTitle">
                    ثبت مهمان
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>نام</span>
                    <input
                        name="first_name"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>نام خانوادگی</span>
                    <input
                        name="last_name"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>موبایل</span>
                    <input
                        name="mobile"
                        dir="ltr"
                    >
                </label>

                <label class="portal-field">
                    <span>پلاک خودرو</span>
                    <input
                        name="vehicle_plate"
                    >
                </label>

                <label class="portal-field">
                    <span>زمان ورود مورد انتظار</span>
                    <input
                        type="datetime-local"
                        name="expected_entry_at"
                    >
                </label>

                <label class="portal-field">
                    <span>زمان خروج مورد انتظار</span>
                    <input
                        type="datetime-local"
                        name="expected_exit_at"
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>توضیحات</span>
                    <textarea
                        name="description"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت مهمان
                </button>
            </div>
        </form>
    </div>
</div>


@endif

@if(($resource ?? '') === 'services')
{{-- Service Modal --}}
<div
    class="modal fade"
    id="serviceModal"
    tabindex="-1"
    aria-labelledby="serviceModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="service"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="serviceModalTitle">
                    درخواست خدمات ساختمان
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>نوع خدمت</span>
                    <select
                        name="type"
                        required
                    >
                        <option value="electrical">
                            برق
                        </option>
                        <option value="plumbing">
                            آب و فاضلاب
                        </option>
                        <option value="elevator">
                            آسانسور / درب
                        </option>
                        <option value="heating">
                            موتورخانه / گرمایش
                        </option>
                        <option value="cleaning">
                            نظافت
                        </option>
                        <option value="garden">
                            فضای سبز
                        </option>
                        <option value="other">
                            سایر
                        </option>
                    </select>
                </label>

                <label class="portal-field">
                    <span>اولویت</span>
                    <select name="priority">
                        <option value="normal">عادی</option>
                        <option value="low">کم</option>
                        <option value="high">زیاد</option>
                        <option value="urgent">فوری</option>
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>عنوان</span>
                    <input
                        name="title"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>شرح درخواست</span>
                    <textarea
                        name="description"
                        rows="4"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت درخواست
                </button>
            </div>
        </form>
    </div>
</div>


@endif

@if(($resource ?? '') === 'support')
{{-- Support Modal --}}
<div
    class="modal fade"
    id="supportModal"
    tabindex="-1"
    aria-labelledby="supportModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="support"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="supportModalTitle">
                    ثبت تیکت پشتیبانی
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>دسته‌بندی</span>
                    <select name="support_category_id">
                        <option value="">
                            عمومی
                        </option>
                        @foreach (
                            $portalData[
                                'support_categories'
                            ]
                            as $category
                        )
                            <option
                                value="{{ $category->id }}"
                            >
                                {{ $category->title }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>اولویت</span>
                    <select name="priority">
                        <option value="medium">عادی</option>
                        <option value="low">کم</option>
                        <option value="high">زیاد</option>
                        <option value="urgent">فوری</option>
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>موضوع</span>
                    <input
                        name="subject"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>شرح مشکل</span>
                    <textarea
                        name="description"
                        rows="4"
                        required
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ثبت تیکت
                </button>
            </div>
        </form>
    </div>
</div>


@endif

@if(($resource ?? '') === 'reservations')
{{-- Reservation Modal --}}
<div
    class="modal fade"
    id="reservationModal"
    tabindex="-1"
    aria-labelledby="reservationModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="reservation"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="reservationModalTitle">
                    رزرو امکانات
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <label class="portal-field portal-field--wide">
                    <span>امکانات</span>
                    <select
                        name="facility_id"
                        required
                    >
                        @foreach (
                            $portalData[
                                'facilities'
                            ]
                            as $facility
                        )
                            <option
                                data-currency="{{ $facility->building?->currency ?? 'IRR' }}"
                                data-price="{{ $facility->default_price }}"
                                data-requires-payment="{{ $facility->requires_payment ? '1' : '0' }}"
                                data-timezone="{{ $facility->building?->timezone ?: 'Asia/Tehran' }}"
                                data-policy="{{ $facility->resident_policy }}"
                                value="{{
                                    $facility->id
                                }}"
                                data-building-id="{{
                                    $facility
                                        ->building_id
                                }}"
                            >
                                {{
                                    $facility
                                        ->building
                                        ?->title
                                }}
                                /
                                {{
                                    $facility
                                        ->title
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field portal-field--wide">
                    <span>واحد</span>
                    <select
                        name="unit_id"
                        required
                    >
                        @foreach ($portalData['units'] as $unit)
                            <option
                                value="{{ $unit['id'] }}"
                                data-building-id="{{ $unit['building_id'] }}"
                            >
                                {{
                                    $unit['building_title']
                                }}
                                /
                                {{
                                    $unit['title']
                                }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="portal-field">
                    <span>تاریخ رزرو</span>
                    <input
                        type="date"
                        name="reservation_date"
                        min="{{ now()->toDateString() }}"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>ساعت شروع</span>
                    <input
                        type="time"
                        name="start_time"
                        required
                    >
                </label>

                <label class="portal-field">
                    <span>ساعت پایان</span>
                    <input
                        type="time"
                        name="end_time"
                        required
                    >
                </label>

                <label class="portal-field portal-field--wide">
                    <span>توضیحات</span>
                    <textarea
                        name="description"
                        rows="3"
                    ></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                    @disabled(
                        $portalData[
                            'facilities'
                        ]->isEmpty()
                    )
                >
                    ثبت رزرو
                </button>
            </div>
        </form>
    </div>
</div>


@endif

@if(($resource ?? '') === 'reservations')
{{-- Reservation Wallet Payment Modal --}}
<div
    class="modal fade"
    id="reservationPaymentModal"
    tabindex="-1"
    aria-labelledby="reservationPaymentModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="reservation-payment"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="reservationPaymentModalTitle">
                    پرداخت رزرو از کیف پول
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <input
                    type="hidden"
                    name="reservation_id"
                >

                <div class="portal-topup-target portal-field--wide">
                    <span>
                        رزرو:
                    </span>
                    <strong data-reservation-payment-title>
                        —
                    </strong>
                </div>

                <label class="portal-field portal-field--wide">
                    <span>
                        منبع پرداخت
                    </span>

                    <select
                        name="payer_source"
                        required
                    >
                        <option value="unit_wallet">
                            کیف پول واحد
                        </option>
                        <option value="user_wallet">
                            کیف پول شخصی
                        </option>
                    </select>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    پرداخت رزرو
                </button>
            </div>
        </form>
    </div>
</div>


@endif

@if(($resource ?? '') === 'support')
{{-- Ticket Conversation Modal --}}
<div
    class="modal fade"
    id="ticketConversationModal"
    tabindex="-1"
    aria-labelledby="ticketConversationModalTitle"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content portal-modal">
            <div class="modal-header">
                <div>
                    <span class="portal-eyebrow">
                        گفت‌وگوی پشتیبانی
                    </span>
                    <h5
                        class="modal-title" id="ticketConversationModalTitle"
                        data-ticket-conversation-title
                    >
                        گفتگو با پشتیبانی
                    </h5>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body">
                <div
                    class="portal-ticket-conversation"
                    data-ticket-message-list
                >
                    <div class="portal-empty-mini">
                        در حال دریافت پیام‌ها...
                    </div>
                </div>

                <form
                    class="portal-ticket-reply" aria-label="پاسخ به پشتیبانی"
                    data-ticket-reply-form
                >
                    <input
                        type="hidden"
                        name="ticket_id"
                    >

                    <textarea
                        name="message"
                        aria-label="متن پیام"
                        rows="3"
                        placeholder="پیام خود را بنویسید..."
                        required
                    ></textarea>

                    <button
                        type="submit"
                        class="portal-primary-button"
                    >
                        ارسال پیام
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>


@endif

@if(($resource ?? '') === 'units')
@if (
    $portalData[
        'payment_gateway'
    ]['enabled']
)
<div
    class="modal fade"
    id="topUpModal"
    tabindex="-1"
    aria-labelledby="topUpModalTitle"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content portal-modal"
            data-portal-form="topup"
        >
            <div class="modal-header">
                <h5 class="modal-title" id="topUpModalTitle">
                    افزایش موجودی کیف پول
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="بستن"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body portal-form-grid">
                <input
                    type="hidden"
                    name="unit_id"
                >
                <input
                    type="hidden"
                    name="building_id"
                >

                <div class="portal-topup-target portal-field--wide">
                    <span>
                        کیف پول:
                    </span>
                    <strong data-topup-title>
                        واحد
                    </strong>
                </div>

                <label class="portal-field portal-field--wide">
                    <span>مبلغ</span>
                    <input
                        type="number"
                        name="amount"
                        min="1"
                        required
                        dir="ltr"
                    >
                </label>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    انصراف
                </button>

                <button
                    type="submit"
                    class="portal-primary-button"
                >
                    ادامه پرداخت
                </button>
            </div>
        </form>
    </div>
</div>
@endif


@endif
