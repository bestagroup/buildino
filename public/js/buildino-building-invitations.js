(() => {
    'use strict';
    const root = document.querySelector('[data-building-invitations]');
    if (!root?.dataset.sendUrl) return;
    const hours = root.querySelector('#invitation-hours');
    const status = root.querySelector('[data-send-result]');
    let busy = false;
    const digits = value => new Intl.NumberFormat('fa-IR', { useGrouping: false }).format(value);
    const notify = (message, state = '') => {
        status.hidden = false;
        status.textContent = message;
        status.dataset.state = state;
    };
    const updateSelection = () => {
        const available = [...root.querySelectorAll('[data-recipient]:not(:disabled)')];
        const selected = available.filter(input => input.checked).length;
        const all = root.querySelector('[data-select-page]');
        if (all) {
            all.checked = available.length > 0 && selected === available.length;
            all.indeterminate = selected > 0 && selected < available.length;
            all.disabled = busy || !available.length;
        }
        const count = root.querySelector('[data-selected-count]');
        if (count) count.textContent = `${digits(selected)} انتخاب‌شده`;
        const button = root.querySelector('[data-send-selected]');
        if (button) button.disabled = busy || selected === 0;
    };
    const formatDates = () => root.querySelectorAll('[data-invitation-date]').forEach(time => {
        const date = new Date(time.dateTime);
        if (!Number.isNaN(date.getTime())) time.textContent = new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Tehran' }).format(date);
    });
    updateSelection();
    formatDates();
    root.querySelector('[data-select-page]')?.addEventListener('change', event => {
        root.querySelectorAll('[data-recipient]:not(:disabled)').forEach(input => { input.checked = event.target.checked; });
        updateSelection();
    });
    root.addEventListener('change', event => { if (event.target.matches('[data-recipient]')) updateSelection(); });
    const send = async elements => {
        if (busy) return;
        if (!hours) return;
        const expiry = Number(window.BuildinoNumeric?.normalize(hours.value) ?? hours.value);
        if (!hours.checkValidity() || !Number.isInteger(expiry) || expiry < 1 || expiry > 720) { hours.reportValidity(); return; }
        if (!elements.length) { notify('حداقل یک مخاطب انتخاب کنید.', 'error'); return; }
        busy = true;
        const controls = [...root.querySelectorAll('button,input,select')].map(el => [el, el.disabled]);
        controls.forEach(([el]) => { el.disabled = true; });
        root.setAttribute('aria-busy', 'true');
        let succeeded = 0;
        const resendResults = new Map();
        for (const [index, el] of elements.entries()) {
            const result = el.closest('tr').querySelector('[data-row-result]');
            result.textContent = 'در حال ارسال…';
            result.dataset.state = 'pending';
            notify(`ارسال ${digits(index + 1)} از ${digits(elements.length)}`);
            try {
                const response = await fetch(root.dataset.sendUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ unit_id: Number(el.dataset.unit), user_id: Number(el.dataset.user), invitation_id: el.dataset.resend ? Number(el.dataset.resend) : null, expires_in_hours: expiry }),
                });
                const body = await response.json();
                if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'ارسال ناموفق بود.');
                result.textContent = 'ارسال به درگاه تأیید شد.';
                result.dataset.state = 'success';
                el.checked = false;
                succeeded++;
            } catch (error) { result.textContent = error.message || 'ارتباط قطع شد؛ پیش از تلاش مجدد سوابق را بررسی کنید.'; result.dataset.state = 'error'; }
            if (el.dataset.resend) resendResults.set(el.dataset.resend, { text: result.textContent, state: result.dataset.state });
        }
        notify(`${digits(succeeded)} ارسال تأیید شد؛ ${digits(elements.length - succeeded)} ارسال تأیید نشد.`, succeeded === elements.length ? 'success' : 'error');
        // Reload only the history region; retain individual outcomes and selections on screen.
        try {
            const page = await fetch(root.querySelector('[data-refresh-invitations]').href, { credentials: 'same-origin' });
            if (!page.ok) throw new Error();
            const html = new DOMParser().parseFromString(await page.text(), 'text/html');
            const history = html.querySelector('#invitation-history');
            if (history) {
                history.querySelectorAll('[data-resend]').forEach(button => {
                    const outcome = resendResults.get(button.dataset.resend);
                    if (outcome) {
                        const result = button.closest('tr').querySelector('[data-row-result]');
                        result.textContent = outcome.text;
                        result.dataset.state = outcome.state;
                    }
                });
                root.querySelector('#invitation-history').replaceWith(history);
                formatDates();
            }
        } catch { status.textContent += ' برای مشاهده سوابق جدید، به‌روزرسانی فهرست را بزنید.'; }
        controls.forEach(([el, disabled]) => { el.disabled = disabled; });
        root.removeAttribute('aria-busy');
        busy = false;
        updateSelection();
    };
    root.querySelector('[data-send-selected]')?.addEventListener('click', () => send([...root.querySelectorAll('[data-recipient]:checked:not(:disabled)')]));
    root.addEventListener('click', event => { const button = event.target.closest('[data-resend]'); if (button) send([button]); });
})();
