(() => {
    const panel = document.getElementById('charge-sms-panel');
    if (!panel) return;
    const selectAll = document.getElementById('charge-select-all');
    const button = document.getElementById('charge-send');
    const summary = document.getElementById('charge-result');
    selectAll.addEventListener('change', () => {
        panel.querySelectorAll('.charge-recipient:not(:disabled)').forEach(input => { input.checked = selectAll.checked; });
    });
    button.addEventListener('click', async () => {
        const selected = [...panel.querySelectorAll('.charge-recipient:checked:not(:disabled)')];
        if (!selected.length) { summary.textContent = 'حداقل یک مخاطب انتخاب کنید.'; return; }
        button.disabled = true;
        selectAll.disabled = true;
        let accepted = 0;
        for (const input of selected) {
            const status = input.closest('tr').querySelector('[data-send-status]');
            input.disabled = true;
            status.textContent = 'در حال ارسال…';
            try {
                const response = await fetch(panel.dataset.sendUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ user_id: Number(input.value) })
                });
                const result = await response.json();
                if (!response.ok) {
                    status.textContent = result.message || 'ارسال انجام نشد؛ صفحه را تازه کنید.';
                } else if (result.status === 'accepted') {
                    accepted++;
                    status.textContent = 'پذیرفته‌شده توسط قاصدک';
                } else {
                    status.textContent = 'نیازمند بررسی نتیجه در قاصدک؛ ارسال مجدد انجام نشد.';
                }
            } catch (_) { status.textContent = 'نتیجه نامشخص؛ سوابق را پس از تازه‌سازی بررسی کنید.'; }
        }
        summary.textContent = `${accepted} درخواست پذیرفته‌شده. برای مشاهده سوابق به‌روز صفحه را تازه کنید.`;
        button.disabled = false;
        selectAll.disabled = false;
    });
})();
