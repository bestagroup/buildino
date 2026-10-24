(() => {
    'use strict';
    const root = document.querySelector('[data-reservation-page]');
    if (!root) return;
    const $ = key => root.querySelector(`[data-reservation-${key}]`);
    const config = window.BuildinoReservationConfig;
    const buildings = new Map(config.buildings.map(row => [String(row.id), row]));
    const buildingSelect = $('building'), availabilityForm = $('availability-form'), createForm = $('create-form');
    const request = (...args) => window.BuildinoCoreFlows.request(...args);
    const statuses = { pending: 'در انتظار تأیید مدیر', payment_pending: 'در انتظار پرداخت', approved: 'تأییدشده', confirmed: 'قطعی', rejected: 'ردشده', cancelled: 'لغوشده', completed: 'پایان‌یافته', expired: 'منقضی‌شده' };
    const number = value => new Intl.NumberFormat('fa-IR').format(value);
    const currency = () => ({ IRR: 'ریال', IRT: 'تومان' }[buildings.get(buildingSelect.value)?.currency] || buildings.get(buildingSelect.value)?.currency || 'واحد پول ساختمان');
    const money = value => `${number(value)} ${currency()}`;
    const time = value => String(value || '').slice(0, 5);
    const timeRange = (start, end) => `\u2066${time(start)} – ${time(end)}\u2069`;
    const date = value => value ? new Date(`${String(value).slice(0, 10)}T12:00:00`).toLocaleDateString('fa-IR') : 'نامشخص';
    const dateTime = value => value ? new Date(value).toLocaleString('fa-IR') : 'تعیین نشده';
    const make = (tag, cls, text) => { const el = document.createElement(tag); el.className = cls || ''; if (text !== undefined) el.textContent = text; return el; };
    const button = (label, action, control = '') => { const el = make('button', 'ownership-button', label); el.type = 'button'; el.dataset.reservationControl = control; el.addEventListener('click', () => { if (!busy && !el.disabled) action(); }); return el; };
    const card = (title, lines) => { const el = make('article', 'ownership-record'), copy = make('div', 'ownership-record__main'); copy.append(make('strong', '', title)); for (const line of lines) if (line) copy.append(make('small', '', line)); el.append(copy); return el; };
    const building = () => buildings.get(buildingSelect.value);
    const input = (form, key) => form.elements.namedItem(key);
    const selectedDate = () => input(availabilityForm, 'date').value;
    const refresh = select => window.BuildinoSelect2?.refresh(select);
    let facility = null, slot = null, slotDate = '', reservation = null, decision = null, busy = false, detailLoading = false;
    let facilityPage = 1, facilityLast = 1, historyPage = 1, historyLast = 1;
    let facilitiesLoading = false, historyLoading = false, slotsLoading = false, choiceLoading = false;
    let submissionUnknown = false, detailUnknown = false;
    let facilityTicket = 0, choiceTicket = 0, slotTicket = 0, historyTicket = 0, detailTicket = 0, timer;
    const message = (kind, text = '', scope = 'booking') => {
        for (const prefix of ['', 'booking-', 'detail-']) for (const key of ['error', 'success']) {
            const target = $(`${prefix}${key}`), visible = prefix === (scope ? `${scope}-` : '') && key === kind && text;
            target.hidden = !visible; target.textContent = visible ? text : '';
        }
        if (kind === 'error' && text) $(`${scope ? `${scope}-` : ''}error`).focus();
    };
    const report = (error, scope = 'booking') => {
        const translations = {
            'The selected facility time slot has reached capacity.': 'ظرفیت این سانس تکمیل شده است؛ سانس دیگری انتخاب کنید.',
            'The selected facility time overlaps an existing reservation.': 'این بازه با رزرو دیگری تداخل دارد.',
            'The selected facility is inactive.': 'این امکان در حال حاضر فعال نیست.',
            'The selected time slot is inactive.': 'این سانس غیرفعال شده است.',
            'The selected time slot is not available on this date.': 'این سانس در تاریخ انتخاب‌شده در دسترس نیست.',
            'Facility reservations must be in the future.': 'زمان سانس گذشته است؛ سانس آینده را انتخاب کنید.',
            'The reservation does not satisfy the minimum advance time.': 'مهلت لازم پیش از شروع سانس رعایت نشده است.',
            'The reservation is beyond the maximum advance booking window.': 'این تاریخ خارج از بازه مجاز رزرو است.',
            'The reservation duration is shorter than the facility minimum.': 'مدت سانس کمتر از حداقل مدت مجاز است.',
            'The reservation duration exceeds the facility maximum.': 'مدت سانس از حداکثر مدت مجاز بیشتر است.',
            'The facility is unavailable during the selected blackout period.': 'امکان در این بازه به‌طور موقت تعطیل است.',
            'This unit has reached the maximum number of active facility reservations.': 'سهمیه رزرو فعال این واحد تکمیل شده است.',
            'This unit has reached the daily reservation limit.': 'سهمیه رزرو روزانه این واحد تکمیل شده است.',
            'This unit has reached the weekly reservation limit.': 'سهمیه رزرو هفتگی این واحد تکمیل شده است.',
            'This unit has reached the monthly reservation limit.': 'سهمیه رزرو ماهانه این واحد تکمیل شده است.',
            'Insufficient available wallet balance.': 'موجودی قابل استفاده کیف پول کافی نیست.',
            'Reservation is not awaiting payment.': 'این رزرو دیگر در انتظار پرداخت نیست؛ وضعیت را به‌روزرسانی کنید.',
            'This reservation can no longer be cancelled.': 'لغو این رزرو با وضعیت فعلی امکان‌پذیر نیست.',
            'The cancellation deadline for this reservation has passed.': 'مهلت لغو رزرو گذشته است.',
            'Only pending reservations can be approved.': 'فقط رزرو در انتظار تأیید قابل تأیید است.',
            'Only pending reservations can be rejected.': 'فقط رزرو در انتظار تأیید قابل رد است.',
        };
        const errors = Object.values(error.errors || {}).flat();
        message('error', errors.length ? errors.map(text => translations[text] || text).join('؛ ') : error.status ? error.message : 'ارتباط با سرور برقرار نشد. پیش از تکرار عملیات، وضعیت رزروها را به‌روزرسانی کنید.', scope);
    };
    const sync = () => {
        root.setAttribute('aria-busy', String(busy || facilitiesLoading || historyLoading || slotsLoading || choiceLoading || detailLoading));
        root.querySelectorAll('button, input, textarea, select').forEach(el => el.disabled = busy || el.dataset.unavailable === 'true' || el.dataset.reservationControl === 'facility' && choiceLoading || el.dataset.reservationControl === 'slot' && slotsLoading);
        root.querySelectorAll('select').forEach(refresh);
        $('facility-prev').disabled = busy || facilitiesLoading || facilityPage <= 1; $('facility-next').disabled = busy || facilitiesLoading || facilityPage >= facilityLast;
        $('history-prev').disabled = busy || historyLoading || historyPage <= 1; $('history-next').disabled = busy || historyLoading || historyPage >= historyLast;
        $('facility-reload').disabled = busy || facilitiesLoading;
        $('history-reload').disabled = busy || historyLoading;
        availabilityForm.querySelector('[type="submit"]').disabled = busy || slotsLoading || !facility;
        $('create').disabled = busy || submissionUnknown || !slot || !facility || !building()?.canCreate || input(createForm, 'unit_id').options.length < 2;
        $('detail-reload').disabled = busy || detailLoading || !reservation;
        $('actions').querySelectorAll('button').forEach(el => el.disabled = busy || detailLoading || detailUnknown);
        for (const key of ['pay', 'cancel', 'decision']) $(`${key}-form`).querySelector('[type="submit"]').disabled = busy || detailLoading || detailUnknown;
        $('uncertain').hidden = !submissionUnknown;
    };
    const go = step => {
        root.querySelectorAll('[data-reservation-panel]').forEach(el => el.hidden = Number(el.dataset.reservationPanel) !== step);
        root.querySelectorAll('[data-reservation-step-label]').forEach(el => {
            const index = Number(el.dataset.reservationStepLabel); el.className = index === step ? 'is-current' : index < step ? 'is-complete' : '';
            if (index === step) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
        });
        $('booking-title').focus();
    };
    const clearSlots = () => { ++slotTicket; slotsLoading = false; slot = null; slotDate = ''; $('slots').replaceChildren(); $('slot-state').textContent = 'تاریخ را انتخاب کنید و «نمایش سانس‌ها» را بزنید.'; sync(); };
    const today = () => {
        const parts = new Intl.DateTimeFormat('en', { timeZone: building()?.timezone || 'Asia/Tehran', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
        const part = type => parts.find(item => item.type === type).value;
        return `${part('year')}-${part('month')}-${part('day')}`;
    };
    const setDate = value => {
        const field = input(availabilityForm, 'date');
        if (field.dataset.buildinoGregorianValue) {
            field.previousElementSibling.value = window.BuildinoJalaliDatepicker.serverToJalali(value, 'date');
            field.previousElementSibling.dispatchEvent(new Event('input', { bubbles: true }));
        } else { field.value = value; field.dispatchEvent(new Event('input', { bubbles: true })); }
    };
    const closeForms = () => { for (const key of ['pay', 'cancel', 'decision']) $(`${key}-form`).hidden = true; };
    async function loadFacilities(page = facilityPage) {
        if (!building()?.canCreate) return;
        const ticket = ++facilityTicket;
        facilitiesLoading = true; sync();
        $('facilities').replaceChildren(); $('facility-state').textContent = 'در حال دریافت امکانات فعال…';
        $('facility-prev').disabled = true; $('facility-next').disabled = true;
        const query = new URLSearchParams({ is_active: '1', search: $('facility-search').value.trim(), page: String(page) });
        try {
            const payload = await request(`/api/v1/buildings/${buildingSelect.value}/facilities?${query}`);
            if (ticket !== facilityTicket) return;
            facilityPage = payload.meta?.current_page || 1; facilityLast = payload.meta?.last_page || 1;
            $('facility-state').textContent = payload.data.length ? 'امکان مورد نظر را انتخاب کنید.' : 'امکان فعالی با این مشخصات برای رزرو وجود ندارد.';
            for (const row of payload.data) {
                const el = card(row.title, [row.description, row.requires_payment ? `هزینه پایه: ${money(row.default_price)}` : 'بدون نیاز به پرداخت', `ظرفیت پایه: ${row.capacity ? number(row.capacity) : 'تعیین نشده'}؛ ظرفیت رزرو برای هر سانس مشخص می‌شود.`]);
                el.append(button('مشاهده زمان‌های آزاد', () => chooseFacility(row.id), 'facility')); $('facilities').append(el);
            }
            $('facility-page').textContent = `صفحه ${number(facilityPage)} از ${number(facilityLast)}`; sync();
        } catch (error) { if (ticket === facilityTicket) { $('facility-state').textContent = 'دریافت امکانات انجام نشد؛ به‌روزرسانی را بزنید.'; report(error); } }
        finally { if (ticket === facilityTicket) { facilitiesLoading = false; sync(); } }
    }
    async function chooseFacility(id) {
        const ticket = ++choiceTicket; facility = null; clearSlots(); choiceLoading = true; sync(); message('', '');
        $('choice-label').textContent = 'در حال دریافت اطلاعات امکان…';
        try {
            const payload = await request(`/api/v1/facilities/${id}`);
            if (ticket !== choiceTicket) return;
            if (!payload.data.is_active) { message('error', 'این امکان غیرفعال شده است. امکان دیگری انتخاب کنید.'); return; }
            facility = payload.data; $('choice-label').textContent = facility.title;
            $('facility-info').replaceChildren(card(facility.title, [facility.description, facility.requires_payment ? 'برای این امکان پرداخت لازم است؛ مبلغ هر سانس جداگانه نمایش داده می‌شود.' : 'استفاده از این امکان نیاز به پرداخت ندارد.']));
            const rule = facility.reservation_rule || {};
            $('rules').replaceChildren(card('مدت و بازه رزرو', [
                `حداقل مدت: ${rule.min_duration_minutes ? `${number(rule.min_duration_minutes)} دقیقه` : 'تعیین نشده'} · حداکثر مدت: ${rule.max_duration_minutes ? `${number(rule.max_duration_minutes)} دقیقه` : 'تعیین نشده'}`,
                `فاصله لازم تا شروع: ${number(rule.min_advance_minutes || 0)} دقیقه · رزرو تا ${rule.max_advance_days ? `${number(rule.max_advance_days)} روز آینده` : 'بازه تعیین‌نشده'}`,
            ]), card('سهمیه و شرایط لغو', [
                `حداکثر رزرو فعال واحد: ${rule.max_reservation_per_unit ? number(rule.max_reservation_per_unit) : 'تعیین نشده'}`,
                `سهمیه روزانه: ${rule.max_reservations_per_day ? number(rule.max_reservations_per_day) : 'تعیین نشده'} · هفتگی: ${rule.max_reservations_per_week ? number(rule.max_reservations_per_week) : 'تعیین نشده'} · ماهانه: ${rule.max_reservations_per_month ? number(rule.max_reservations_per_month) : 'تعیین نشده'}`,
                `مهلت لغو: ${number(rule.cancel_before_minutes || 0)} دقیقه پیش از شروع · هزینه لغو: ${money(rule.cancellation_fee || 0)} · درصد بازپرداخت: ${number(rule.refund_percentage ?? 100)}٪`,
                facility.requires_approval || !rule.auto_confirm ? 'رزرو پس از ثبت نیازمند تأیید مدیر است.' : 'تأیید رزرو به‌صورت خودکار انجام می‌شود.',
            ]));
            go(1); if (selectedDate()) await loadSlots();
        } catch (error) { if (ticket === choiceTicket) { $('choice-label').textContent = 'اطلاعات امکان دریافت نشد؛ دوباره انتخاب کنید.'; report(error); } }
        finally { if (ticket === choiceTicket) { choiceLoading = false; sync(); } }
    }
    async function loadSlots() {
        if (!facility) return;
        if (!selectedDate() || !/^\d{4}-\d{2}-\d{2}$/.test(selectedDate()) || selectedDate() < today()) {
            message('error', 'یک تاریخ معتبر از امروز به بعد انتخاب کنید.'); return;
        }
        clearSlots(); const ticket = ++slotTicket, facilityId = facility.id, day = selectedDate();
        slotsLoading = true; sync();
        $('slot-state').textContent = 'در حال بررسی ظرفیت سانس‌ها…';
        try {
            const payload = await request(`/api/v1/facilities/${facilityId}/availability?date=${encodeURIComponent(day)}`);
            if (ticket !== slotTicket) return;
            const availability = payload.data;
            $('slot-state').textContent = !availability.facility_is_active ? 'این امکان دیگر فعال نیست.' : availability.slots.length ? `${number(availability.slots.filter(row => row.is_available).length)} سانس دارای ظرفیت؛ قوانین نهایی هنگام ثبت بررسی می‌شوند.` : 'برای این تاریخ سانسی تعریف نشده است؛ تاریخ دیگری انتخاب کنید.';
            for (const row of availability.slots) {
                const available = row.is_available && availability.facility_is_active;
                const el = card(timeRange(row.start_time, row.end_time), [`${number(row.remaining_capacity)} ظرفیت باقی‌مانده از ${number(row.capacity)}`, facility.requires_payment ? `هزینه: ${money(row.price)}` : 'بدون نیاز به پرداخت']);
                el.classList.toggle('is-unavailable', !available);
                const pick = button(available ? 'انتخاب سانس' : !availability.facility_is_active ? 'امکان غیرفعال است' : row.is_blackout ? 'تعطیلی موقت' : row.remaining_capacity <= 0 ? 'ظرفیت تکمیل' : 'زمان گذشته / غیرقابل رزرو', () => {
                    if (!available) return; slot = row; slotDate = availability.date;
                    $('review').replaceChildren(card(facility.title, [`تاریخ: ${date(slotDate)}`, `سانس: ${timeRange(slot.start_time, slot.end_time)}`, facility.requires_payment && slot.price > 0 ? `مبلغ قابل پرداخت: ${money(slot.price)}` : 'بدون نیاز به پرداخت', 'قیمت و ظرفیت نهایی توسط سامانه بررسی می‌شود.']));
                    sync(); go(2);
                }, 'slot');
                pick.dataset.unavailable = String(!available); pick.disabled = !available; el.append(pick); $('slots').append(el);
            }
        } catch (error) { if (ticket === slotTicket) { $('slot-state').textContent = 'دریافت سانس‌ها انجام نشد؛ دوباره تلاش کنید.'; report(error); } }
        finally { if (ticket === slotTicket) { slotsLoading = false; sync(); } }
    }
    async function loadHistory(page = historyPage) {
        if (!building()) return;
        const ticket = ++historyTicket;
        historyLoading = true; sync();
        $('history-list').replaceChildren(); $('history-state').textContent = 'در حال دریافت رزروها…';
        $('history-prev').disabled = true; $('history-next').disabled = true;
        const query = new URLSearchParams({ building_id: buildingSelect.value, status: $('status').value, page: String(page) });
        try {
            const payload = await request(`/api/v1/facility-reservations?${query}`);
            if (ticket !== historyTicket) return;
            historyPage = payload.meta?.current_page || 1; historyLast = payload.meta?.last_page || 1;
            $('history-state').textContent = payload.data.length ? `${number(payload.meta?.total ?? payload.data.length)} رزرو` : 'رزروی با این وضعیت وجود ندارد.';
            for (const row of payload.data) {
                const el = card(`${row.facility?.title || 'رزرو'} · شماره ${number(row.id)}`, [`${date(row.reservation_date)} · ${timeRange(row.start_time, row.end_time)}`, `واحد ${row.unit?.unit_number || 'نامشخص'} · مبلغ ثبت‌شده: ${money(row.final_amount)}`]);
                el.append(make('span', `reservation-status reservation-status--${Object.hasOwn(statuses, row.status) ? row.status : 'unknown'}`, statuses[row.status] || 'نامشخص'));
                el.append(button('جزئیات و پیگیری', () => showReservation(row.id))); $('history-list').append(el);
            }
            $('history-page').textContent = `صفحه ${number(historyPage)} از ${number(historyLast)}`; sync();
        } catch (error) { if (ticket === historyTicket) { $('history-state').textContent = 'دریافت رزروها انجام نشد؛ به‌روزرسانی را بزنید.'; report(error, ''); } }
        finally { if (ticket === historyTicket) { historyLoading = false; sync(); } }
    }
    const owned = row => Number(row.user_id) === Number(config.actorId);
    const resultText = row => ({ payment_pending: 'درخواست رزرو ثبت شد؛ برای ادامه پرداخت لازم است.', pending: 'درخواست رزرو ثبت شد و در انتظار تأیید مدیر است.', approved: 'رزرو تأیید شد.', confirmed: 'رزرو قطعی شد.' }[row.status] || `وضعیت رزرو: ${statuses[row.status] || 'نامشخص'}`);
    function renderReservation() {
        const row = reservation; if (!row) return;
        closeForms(); $('detail').hidden = false;
        $('detail-state').textContent = `شماره ${number(row.id)} · ${statuses[row.status] || 'وضعیت نامشخص'}`;
        $('detail-body').replaceChildren(card(row.facility?.title || 'رزرو امکان', [`تاریخ: ${date(row.reservation_date)} · ${timeRange(row.start_time, row.end_time)}`, `واحد ${row.unit?.unit_number || 'نامشخص'} · رزروکننده: ${row.user?.first_name || ''} ${row.user?.last_name || ''}`, `مبلغ ثبت‌شده: ${money(row.final_amount)}`, row.description, row.expires_at ? `مهلت رزرو: ${dateTime(row.expires_at)}` : '']));
        if (row.wallet_payment) $('detail-body').append(card('پرداخت کیف پول', [`مبلغ: ${money(row.wallet_payment.amount)} · ${row.wallet_payment.status === 'paid' ? 'پرداخت‌شده' : 'وضعیت پرداخت: ' + row.wallet_payment.status}`, `تاریخ پرداخت: ${dateTime(row.wallet_payment.paid_at)}`]));
        for (const item of row.cancellations || []) $('detail-body').append(card('سابقه لغو', [item.reason, `هزینه لغو: ${money(item.cancellation_fee)} · مبلغ بازپرداخت: ${money(item.refund_amount)}`, `وضعیت بازپرداخت: ${{ pending: 'در انتظار بازپرداخت', refunded: 'بازپرداخت‌شده', cancelled: 'لغوشده', failed: 'ناموفق' }[item.refund_status] || 'بازپرداختی ثبت نشده'}`]));
        $('actions').replaceChildren();
        if (row.status === 'payment_pending' && owned(row) && !row.wallet_payment) $('actions').append(button('ادامه و پرداخت از کیف پول', () => {
            closeForms(); $('pay-form').reset(); $('pay-form').hidden = false; $('pay-amount').textContent = `مبلغ قابل برداشت: ${money(row.final_amount)}`; input($('pay-form'), 'payer_source').focus();
        }));
        if (['pending', 'payment_pending', 'approved', 'confirmed'].includes(row.status) && (owned(row) || building()?.canCancel)) $('actions').append(button('لغو رزرو', () => {
            closeForms(); $('cancel-form').reset(); $('cancel-form').hidden = false;
            const rule = row.rule_snapshot || {};
            $('cancel-terms').textContent = `مهلت لغو: ${number(rule.cancel_before_minutes || 0)} دقیقه قبل از شروع؛ هزینه لغو طبق قوانین: ${money(rule.cancellation_fee || 0)}؛ درصد بازپرداخت: ${number(rule.refund_percentage ?? 100)}٪. نتیجه و مبلغ نهایی را سامانه تعیین می‌کند.${!owned(row) && building()?.canCancel ? ' لغو توسط شما با اختیار مدیریتی انجام می‌شود.' : ''}`;
            input($('cancel-form'), 'reason').focus();
        }));
        if (row.status === 'pending' && building()?.canApprove) for (const [action, label] of [['approve', 'تأیید رزرو'], ['reject', 'رد رزرو']]) $('actions').append(button(label, () => {
            closeForms(); decision = action; $('decision-form').hidden = false; $('decision-title').textContent = label;
        }));
        $('detail-title').focus();
    }
    async function showReservation(id) {
        const ticket = ++detailTicket; reservation = null; detailLoading = true; closeForms();
        message('', '', 'detail');
        sync();
        $('detail').hidden = false; $('detail-body').replaceChildren(); $('actions').replaceChildren(); $('detail-state').textContent = 'در حال دریافت رزرو…';
        try {
            const payload = await request(`/api/v1/facility-reservations/${id}`);
            if (ticket !== detailTicket) return;
            reservation = payload.data; detailUnknown = false; renderReservation();
        } catch (error) { if (ticket === detailTicket) { $('detail-state').textContent = 'دریافت جزئیات انجام نشد.'; $('detail-body').append(button('تلاش مجدد', () => showReservation(id))); report(error, 'detail'); } }
        finally { if (ticket === detailTicket) { detailLoading = false; sync(); } }
    }
    createForm.addEventListener('submit', async event => {
        event.preventDefault(); if (busy || submissionUnknown || !slot || !facility || !building()?.canCreate || !createForm.reportValidity()) return;
        const facilityId = facility.id, pickedSlot = slot.id;
        busy = true; sync(); message('', '');
        try {
            const payload = await request(`/api/v1/facilities/${facilityId}/reservations`, { method: 'POST', body: JSON.stringify({ facility_time_slot_id: pickedSlot, unit_id: Number(input(createForm, 'unit_id').value), reservation_date: slotDate, description: input(createForm, 'description').value.trim() || null }) });
            slot = null; ++detailTicket; reservation = payload.data; detailLoading = false; detailUnknown = false;
            input(createForm, 'description').value = '';
            go(1); renderReservation(); message('success', resultText(payload.data), 'detail');
            await Promise.allSettled([loadHistory(1), loadSlots()]);
        } catch (error) {
            if (!error.status || error.status >= 500) {
                submissionUnknown = true;
                message('error', 'نتیجه ثبت درخواست مشخص نشد. پیش از ثبت دوباره، فهرست رزروها را بررسی کنید.');
                await loadHistory(1);
            } else if (error.status === 422 && Object.keys(error.errors || {}).some(key => key === 'unit_id' || key === 'description')) {
                report(error);
            } else {
                go(1); await loadSlots(); report(error);
            }
        }
        finally { busy = false; sync(); }
    });
    async function mutate(action, body = {}) {
        if (busy || detailLoading || detailUnknown || !reservation) return;
        if (action === 'pay' && (!owned(reservation) || reservation.status !== 'payment_pending')) return;
        if (['approve', 'reject'].includes(action) && !building()?.canApprove) return;
        if (action === 'cancel' && !owned(reservation) && !building()?.canCancel) return;
        const id = reservation.id; busy = true; sync(); message('', '');
        try {
            const payload = await request(`/api/v1/facility-reservations/${id}/${action}`, { method: 'POST', body: JSON.stringify(body) });
            reservation = payload.data; renderReservation();
            message('success', action === 'pay' ? `پرداخت انجام شد؛ ${resultText(reservation)}` : action === 'cancel' ? 'رزرو لغو شد. وضعیت بازپرداخت را در جزئیات ببینید.' : resultText(reservation), 'detail');
            await loadHistory();
        } catch (error) {
            if (!error.status || error.status >= 500) {
                detailUnknown = true;
                message('error', 'نتیجه این اقدام مشخص نشد. پیش از تکرار پرداخت یا لغو، «به‌روزرسانی وضعیت» را بزنید.', 'detail');
            } else {
                // Keep the entered reason and wallet selection when validation fails.
                report(error, 'detail');
            }
        }
        finally { busy = false; sync(); }
    }
    $('pay-form').addEventListener('submit', event => { event.preventDefault(); if ($('pay-form').reportValidity()) mutate('pay', { payer_source: input($('pay-form'), 'payer_source').value }); });
    $('cancel-form').addEventListener('submit', event => { event.preventDefault(); if ($('cancel-form').reportValidity()) mutate('cancel', { reason: input($('cancel-form'), 'reason').value.trim() || null }); });
    $('decision-form').addEventListener('submit', event => { event.preventDefault(); if (decision) mutate(decision); });
    for (const key of ['pay', 'cancel', 'decision']) $(`${key}-close`).addEventListener('click', () => { if (!busy) { closeForms(); $('detail-title').focus(); } });
    availabilityForm.addEventListener('submit', event => { event.preventDefault(); if (!busy && !slotsLoading && availabilityForm.reportValidity()) { message('', ''); loadSlots(); } });
    availabilityForm.addEventListener('input', clearSlots); availabilityForm.addEventListener('change', clearSlots);
    root.querySelectorAll('[data-reservation-day]').forEach(button => button.addEventListener('click', () => {
        if (busy || !facility) return;
        const day = new Date(`${today()}T12:00:00Z`); day.setUTCDate(day.getUTCDate() + Number(button.dataset.reservationDay));
        message('', ''); setDate(day.toISOString().slice(0, 10)); loadSlots();
    }));
    $('back-facility').addEventListener('click', () => { if (!busy) { ++choiceTicket; clearSlots(); go(0); } });
    $('back-slots').addEventListener('click', () => { if (!busy) { go(1); loadSlots(); } });
    $('facility-reload').addEventListener('click', () => { if (!busy) loadFacilities(); });
    $('facility-prev').addEventListener('click', () => { if (!busy && facilityPage > 1) loadFacilities(facilityPage - 1); });
    $('facility-next').addEventListener('click', () => { if (!busy && facilityPage < facilityLast) loadFacilities(facilityPage + 1); });
    $('facility-search').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { if (!busy) loadFacilities(1); }, 350); });
    $('history-reload').addEventListener('click', () => { if (!busy) loadHistory(); });
    $('history-prev').addEventListener('click', () => { if (!busy && historyPage > 1) loadHistory(historyPage - 1); });
    $('history-next').addEventListener('click', () => { if (!busy && historyPage < historyLast) loadHistory(historyPage + 1); });
    $('status').addEventListener('change', () => { if (!busy) loadHistory(1); });
    $('detail-close').addEventListener('click', () => { if (!busy) { ++detailTicket; reservation = null; detailLoading = false; $('detail').hidden = true; $('history-reload').focus(); } });
    $('detail-reload').addEventListener('click', () => { if (!busy && !detailLoading && reservation) showReservation(reservation.id); });
    $('check-history').addEventListener('click', async () => { if (!busy) { await loadHistory(1); $('history-reload').focus(); } });
    $('resume').addEventListener('click', () => { if (!busy) { submissionUnknown = false; message('', ''); go(1); loadSlots(); sync(); } });
    buildingSelect.addEventListener('change', () => {
        if (busy) return;
        clearTimeout(timer); facilitiesLoading = historyLoading = choiceLoading = false;
        submissionUnknown = detailUnknown = false;
        ++facilityTicket; ++choiceTicket; ++historyTicket; ++detailTicket; clearSlots(); reservation = null; facility = null; detailLoading = false;
        $('detail').hidden = true; $('history').hidden = !building(); $('booking').hidden = !building()?.canCreate;
        $('choice-label').textContent = 'یک امکان فعال را انتخاب کنید.'; $('facility-search').value = ''; $('status').value = ''; refresh($('status'));
        createForm.reset(); availabilityForm.reset(); message('', ''); go(0);
        const select = input(createForm, 'unit_id'); select.replaceChildren(new Option('واحد را انتخاب کنید', ''));
        for (const row of config.units.filter(unit => String(unit.buildingId) === buildingSelect.value)) select.add(new Option(row.label, row.id));
        $('unit-state').textContent = select.options.length < 2 ? 'برای این ساختمان واحدی جهت رزرو وجود ندارد؛ ابتدا ساختار واحدها را تکمیل کنید.' : 'واحدی را انتخاب کنید که رزرو برای آن ثبت می‌شود.';
        $('no-units').hidden = select.options.length >= 2;
        if (select.options.length === 2) select.selectedIndex = 1; refresh(select);
        sync();
        if (building()) { loadHistory(1); if (building().canCreate) loadFacilities(1); }
    });
    if (buildingSelect.options.length === 2) { buildingSelect.selectedIndex = 1; refresh(buildingSelect); buildingSelect.dispatchEvent(new Event('change')); }
})();
