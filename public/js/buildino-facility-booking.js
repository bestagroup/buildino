(() => {
    'use strict';
    const digits = value => String(value ?? '').replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
    const clock = value => digits(String(value || '').slice(0, 5));
    function create({ request, change }) {
        let ticket = 0, state = { phase: 'idle', selection: null, manualAllowed: false, availability: null };
        const emit = patch => { state = { ...state, ...patch }; change(state); };
        const reset = () => { ++ticket; emit({ phase: 'idle', selection: null, manualAllowed: false, availability: null, facility: null, date: '', error: null }); };
        return {
            get state() { return state; },
            get selection() { return state.selection; },
            reset,
            async load(facility, date) {
                const current = ++ticket;
                emit({ phase: 'loading', facility, date, selection: null, manualAllowed: false, availability: null, error: null });
                if (!facility?.id || !/^\d{4}-\d{2}-\d{2}$/.test(date || '')) { emit({ phase: 'idle' }); return; }
                try {
                    const result = await request(`/api/v1/facilities/${facility.id}/availability?date=${encodeURIComponent(date)}`);
                    if (ticket !== current) return;
                    const availability = result.data;
                    if (!availability || !Array.isArray(availability.slots)) throw new Error('پاسخ زمان‌بندی معتبر نیست.');
                    const activeSchedules = (facility.schedules || []).filter(row => row.is_active);
                    const manualAllowed = Boolean(availability.facility_is_active && !availability.slots.length && ((availability.schedules || []).length || !activeSchedules.length));
                    emit({ phase: 'ready', availability, manualAllowed });
                } catch (error) { if (ticket === current) emit({ phase: 'error', error }); }
            },
            pick(id) {
                const row = state.availability?.slots.find(slot => String(slot.id) === String(id));
                if (state.phase !== 'ready' || !state.availability.facility_is_active || !row?.is_available) return;
                emit({ selection: { slotId: row.id, date: state.date, start_time: row.start_time, end_time: row.end_time, price: row.price ?? state.facility.default_price ?? 0 } });
            },
            manual(start, end) {
                const valid = state.phase === 'ready' && state.manualAllowed && /^\d{2}:\d{2}$/.test(start) && /^\d{2}:\d{2}$/.test(end) && end > start;
                emit({ selection: valid ? { slotId: null, date: state.date, start_time: start, end_time: end, price: state.facility.default_price || 0 } : null });
            },
        };
    }
    const status = state => state.phase === 'loading' ? 'در حال دریافت زمان‌ها…' : state.phase === 'error' ? 'زمان‌ها دریافت نشدند؛ دوباره تلاش کنید.' : state.phase !== 'ready' ? 'امکان و تاریخ را انتخاب کنید.' : !state.availability.facility_is_active ? 'این امکان غیرفعال است.' : state.manualAllowed ? 'ساعت شروع و پایان را وارد کنید؛ قوانین هنگام ثبت بررسی می‌شوند.' : state.availability.slots.some(slot => slot.is_available) ? 'سانس موردنظر را انتخاب کنید.' : 'سانس آزادی در این روز وجود ندارد؛ تاریخ دیگری انتخاب کنید.';
    function render(host, state, { money, pick, name = 'facility_slot_choice' }) {
        const key = [state.facility?.id, state.date, state.phase].join(':');
        if (host.dataset.slotContext !== key) {
            host.dataset.slotContext = key; host.replaceChildren();
            for (const slot of state.availability?.slots || []) {
                const available = Boolean(state.availability.facility_is_active && slot.is_available);
                const label = document.createElement('label'); label.className = 'facility-booking-slot';
                const input = document.createElement('input'); input.type = 'radio'; input.name = name; input.value = slot.id; input.disabled = !available;
                input.dataset.unavailable = String(!available);
                const title = document.createElement('strong'); title.textContent = `${clock(slot.start_time)} تا ${clock(slot.end_time)}`;
                const cost = document.createElement('span'); cost.textContent = state.facility.requires_payment ? money(slot.price ?? state.facility.default_price ?? 0) : 'رایگان';
                const capacity = document.createElement('small'); capacity.textContent = available ? `${digits(slot.remaining_capacity)} ظرفیت رزرو باقی‌مانده` : slot.is_blackout ? 'تعطیلی موقت' : slot.remaining_capacity <= 0 ? 'ظرفیت تکمیل' : 'غیرقابل رزرو';
                input.setAttribute('aria-label', `${title.textContent}، ${cost.textContent}، ${capacity.textContent}`);
                input.addEventListener('change', () => pick(slot.id)); label.append(input, title, cost, capacity); host.append(label);
            }
        }
        host.setAttribute('aria-busy', String(state.phase === 'loading'));
        host.querySelectorAll('input[type="radio"]').forEach(input => { input.checked = String(state.selection?.slotId) === input.value; });
    }
    window.BuildinoFacilityBooking = { create, render, status };
})();
