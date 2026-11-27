(() => {
    'use strict';
    const root = document.querySelector('[data-statement-page]');
    if (!root) return;
    const embedded = window.parent !== window && document.querySelector('[data-glance-embedded]');
    const notify = (dirty, busy, changed = false) => {
        if (embedded) window.parent.postMessage({ type: 'buildino:unit-workspace', dirty, busy, changed }, location.origin);
    };
    if (root.hasAttribute('data-statement-saved')) notify(false, false, true);
    const dialog = root.querySelector('#statement-deposit-dialog');
    if (dialog) {
        const form = dialog.querySelector('form'), trigger = root.querySelector('[data-statement-deposit]');
        let dirty = false, busy = false;
        const close = () => { if (!busy && (!dirty || window.confirm('اطلاعات واریزی ذخیره نشده است. از فرم خارج می‌شوید؟'))) dialog.close(); };
        trigger.addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-deposit-close]').forEach(button => button.addEventListener('click', close));
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        dialog.addEventListener('close', () => { notify(false, false); trigger.focus(); });
        form.addEventListener('input', () => { dirty = true; notify(true, busy); });
        form.addEventListener('change', () => { dirty = true; notify(true, busy); });
        form.addEventListener('submit', event => {
            if (busy) { event.preventDefault(); return; }
            busy = true; notify(true, true);
            form.querySelector('[type=submit]').disabled = true;
            form.querySelector('[type=submit]').textContent = 'در حال ثبت…';
            dialog.querySelectorAll('[data-deposit-close]').forEach(button => { button.disabled = true; });
        });
        if (dialog.hasAttribute('data-reopen')) dialog.showModal();
    }
    root.querySelectorAll('[data-statement-settle]').forEach(form => {
        let busy = false, uncertain = false;
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy || uncertain) return;
            busy = true;
            const button = form.querySelector('[type=submit]'), errorBox = form.querySelector('[data-settle-error]');
            button.disabled = true; errorBox.hidden = true;
            const confirmed = window.Swal ? (await window.Swal.fire({ title: 'تسویه صورتحساب', text: form.dataset.confirm, showCancelButton: true, confirmButtonText: 'تأیید و تسویه', cancelButtonText: 'انصراف' })).isConfirmed : window.confirm(form.dataset.confirm);
            if (!confirmed) { busy = false; button.disabled = false; return; }
            button.textContent = 'در حال تسویه…';
            try {
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } });
                uncertain = response.status >= 500;
                const data = await response.json();
                if (!response.ok) {
                    const message = [401,419].includes(response.status) ? 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.' : response.status === 403 ? 'اجازه تسویه این صورتحساب را ندارید.' : Object.values(data.errors || {}).flat().join('؛ ') || data.message || 'تسویه انجام نشد.';
                    throw new Error(message);
                }
                window.location.reload();
            } catch (error) {
                if (error instanceof TypeError || error instanceof SyntaxError) uncertain = true;
                errorBox.hidden = false;
                errorBox.textContent = uncertain ? 'نتیجه تسویه مشخص نیست. پیش از هر اقدام، صفحه را به‌روز کنید.' : error.message;
                if (uncertain) { const reload = document.createElement('button'); reload.type = 'button'; reload.className = 'ownership-button'; reload.textContent = 'به‌روزرسانی'; reload.addEventListener('click', () => window.location.reload()); errorBox.append(document.createTextNode(' '), reload); }
                button.textContent = 'تسویه از کیف پول واحد'; button.disabled = uncertain;
            } finally { busy = false; }
        });
    });
})();
