(() => {
    'use strict';
    const root = document.querySelector('[data-building-invitations]');
    if (!root?.dataset.sendUrl) return;
    const hours = root.querySelector('#invitation-hours');
    const status = root.querySelector('[data-send-result]');
    let busy = false;
    root.querySelector('[data-select-page]')?.addEventListener('change', event => {
        root.querySelectorAll('[data-recipient]:not(:disabled)').forEach(input => { input.checked = event.target.checked; });
    });
    const send = async elements => {
        if (busy) return;
        if (!hours.checkValidity() || !Number.isInteger(Number(hours.value))) { hours.reportValidity(); return; }
        if (!elements.length) { status.textContent = 'حداقل یک مخاطب انتخاب کنید.'; return; }
        busy = true;
        const controls = [...root.querySelectorAll('button,input,select')].map(el => [el, el.disabled]);
        controls.forEach(([el]) => { el.disabled = true; });
        root.setAttribute('aria-busy', 'true');
        let succeeded = 0;
        const resendResults = new Map();
        for (const [index, el] of elements.entries()) {
            const result = el.closest('tr').querySelector('[data-row-result]');
            result.textContent = 'در حال ارسال…';
            status.textContent = `ارسال ${index + 1} از ${elements.length}`;
            try {
                const response = await fetch(root.dataset.sendUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ unit_id: Number(el.dataset.unit), user_id: Number(el.dataset.user), invitation_id: el.dataset.resend ? Number(el.dataset.resend) : null, expires_in_hours: Number(hours.value) }),
                });
                const body = await response.json();
                if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message || 'ارسال ناموفق بود.');
                result.textContent = 'ارسال به درگاه تأیید شد.';
                el.checked = false;
                succeeded++;
            } catch (error) { result.textContent = error.message || 'ارتباط قطع شد؛ پیش از تلاش مجدد سوابق را بررسی کنید.'; }
            if (el.dataset.resend) resendResults.set(el.dataset.resend, result.textContent);
        }
        status.textContent = `${succeeded} ارسال تأیید شد؛ ${elements.length - succeeded} ارسال تأیید نشد.`;
        // Reload only the history region; retain individual outcomes and selections on screen.
        try {
            const page = await fetch(root.querySelector('[data-refresh-invitations]').href, { credentials: 'same-origin' });
            if (!page.ok) throw new Error();
            const html = new DOMParser().parseFromString(await page.text(), 'text/html');
            const history = html.querySelector('#invitation-history');
            if (history) {
                history.querySelectorAll('[data-resend]').forEach(button => {
                    const outcome = resendResults.get(button.dataset.resend);
                    if (outcome) button.closest('tr').querySelector('[data-row-result]').textContent = outcome;
                });
                root.querySelector('#invitation-history').replaceWith(history);
            }
        } catch { status.textContent += ' برای مشاهده سوابق جدید، به‌روزرسانی فهرست را بزنید.'; }
        controls.forEach(([el, disabled]) => { el.disabled = disabled; });
        root.removeAttribute('aria-busy');
        busy = false;
    };
    root.querySelector('[data-send-selected]')?.addEventListener('click', () => send([...root.querySelectorAll('[data-recipient]:checked:not(:disabled)')]));
    root.addEventListener('click', event => { const button = event.target.closest('[data-resend]'); if (button) send([button]); });
})();
