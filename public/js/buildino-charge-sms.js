(() => {
    'use strict';
    const panel = document.getElementById('charge-sms-panel');
    if (!panel) return;
    const all = document.getElementById('charge-select-all'), button = document.getElementById('charge-send');
    const summary = document.getElementById('charge-result'), count = document.getElementById('charge-selected-count');
    const dialog = document.getElementById('charge-confirm'), progress = document.getElementById('charge-progress');
    const eligible = [...panel.querySelectorAll('.charge-recipient:not(:disabled)')];
    const controls = [...document.querySelectorAll('[data-sms-location] select, [data-sms-location] button')];
    let sending = false;
    const available = () => eligible.filter(input => !input.disabled);
    const selected = () => available().filter(input => input.checked);
    const number = value => value.toLocaleString('fa-IR');
    const update = () => {
        const inputs = available(), checked = selected().length;
        all.disabled = sending || !inputs.length;
        all.checked = inputs.length > 0 && checked === inputs.length;
        all.indeterminate = checked > 0 && checked < inputs.length;
        button.disabled = sending || !checked;
        count.textContent = number(checked) + ' مخاطب انتخاب شده';
    };
    all.addEventListener('change', () => { available().forEach(input => { input.checked = all.checked; }); update(); });
    eligible.forEach(input => input.addEventListener('change', update));
    button.addEventListener('click', () => {
        if (sending || !selected().length) return;
        dialog.querySelector('[data-sms-confirm-count]').textContent = 'ارسال پیامک مبلغ محاسبه‌شده دوره به ' + number(selected().length) + ' مخاطب تأیید شود؟';
        dialog.showModal();
    });
    dialog.querySelector('[data-sms-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => button.focus());
    window.addEventListener('beforeunload', event => { if (sending) { event.preventDefault(); event.returnValue = ''; } });
    dialog.querySelector('[data-sms-confirm]').addEventListener('click', async () => {
        if (sending) return;
        const inputs = selected();
        if (!inputs.length) return;
        sending = true; dialog.close();
        const controlStates = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        eligible.forEach(input => { input.disabled = true; });
        all.disabled = true; button.disabled = true; button.textContent = 'در حال ارسال…';
        progress.hidden = false; progress.max = inputs.length; progress.value = 0;
        let accepted = 0, reviewed = 0;
        try {
            for (const input of inputs) {
                const status = input.closest('[data-recipient]').querySelector('[data-send-status]');
                input.dataset.attempted = '1'; input.checked = false; status.textContent = 'در حال ارسال…';
                let stop = false;
                try {
                    const response = await fetch(panel.dataset.sendUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ user_id: Number(input.value) }) });
                    const result = await response.json();
                    if (!response.ok) {
                        status.textContent = [401,419].includes(response.status) ? 'نشست منقضی شده؛ صفحه را تازه کنید.' : response.status === 403 ? 'اجازه ارسال پیامک ندارید.' : response.status === 429 ? 'تعداد درخواست‌ها زیاد است؛ کمی بعد سوابق را بررسی کنید.' : response.status >= 500 ? 'نتیجه نامشخص؛ سوابق را بررسی کنید.' : result.message || 'ارسال انجام نشد؛ سوابق را بررسی کنید.';
                        stop = [401,403,419,429].includes(response.status);
                    } else if (result.status === 'accepted') { accepted++; status.textContent = 'پذیرفته‌شده توسط قاصدک'; }
                    else if (result.status === 'processing') status.textContent = 'در حال ارسال / نیازمند بررسی';
                    else status.textContent = 'نتیجه نامشخص؛ بررسی در قاصدک';
                } catch { status.textContent = 'نتیجه نامشخص؛ سوابق را پس از تازه‌سازی بررسی کنید.'; stop = true; }
                reviewed++; progress.value = reviewed;
                summary.textContent = number(reviewed) + ' از ' + number(inputs.length) + ' بررسی شد · ' + number(accepted) + ' درخواست پذیرفته‌شده';
                if (stop) break;
            }
        } finally {
            sending = false;
            eligible.forEach(input => { input.disabled = input.dataset.attempted === '1'; });
            controls.forEach((control, index) => { control.disabled = controlStates[index]; });
            button.textContent = 'ارسال پیامک به انتخاب‌شده‌ها'; update();
            summary.textContent += '؛ نتیجه‌های دیگر نیازمند بررسی‌اند. برای سوابق به‌روز، صفحه را تازه کنید.';
        }
    });
    update();
})();
