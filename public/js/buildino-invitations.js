(() => {
    'use strict';
    const app = document.querySelector('.invitation-page');
    if (!app) return;
    const $ = selector => app.querySelector(selector);
    const location = $('[data-invitation-location]'), directory = $('[data-invitation-directory]');
    const unit = $('[data-context-name="unit_id"]');
    const unitLabel = () => unit.selectedOptions[0]?.textContent || '';
    const focus = selector => $(selector)?.focus({ preventScroll: true });
    const show = page => {
        location.hidden = page !== 'location';
        directory.hidden = page !== 'directory';
        $('[data-invitation-resume]').hidden = !unit.value;
        $('[data-invitation-unit-label]').textContent = unitLabel();
    };
    unit.addEventListener('change', () => { show(unit.value ? 'directory' : 'location'); focus('#invitation-directory-title'); });
    $('[data-invitation-change-unit]').addEventListener('click', () => { show('location'); focus('#invitation-location-title'); });
    $('[data-invitation-resume]').addEventListener('click', () => { show('directory'); focus('#invitation-directory-title'); });
    app.addEventListener('buildino:inline-editor', event => {
        show(event.detail.open ? 'editor' : unit.value ? 'directory' : 'location');
        if (!event.detail.open) focus('#invitation-directory-title');
    });
    const make = (tag, className, text) => { const el = document.createElement(tag); el.className = className; el.textContent = text; return el; };
    let controller;
    window.BuildinoInvitationUi = {
        get busy() { return Boolean(controller?.busy); },
        mountForm({ form, fields, saveButton, retry }) {
            controller?.destroy();
            const abort = new AbortController(), originalValidation = form.noValidate;
            form.noValidate = true;
            const recipients = fields.querySelector('[name="user_id"]');
            const hours = fields.querySelector('[name="expires_in_hours"]');
            hours.min = '1'; hours.max = '720'; hours.step = '1'; hours.required = true;
            const fieldLabels = [...fields.children];
            const heading = make('h4', 'invitation-step-title', ''); heading.tabIndex = -1;
            const summary = make('dl', 'invitation-review', ''); summary.hidden = true;
            const empty = make('div', 'invitation-note', ''); empty.style.gridColumn = '1 / -1';
            const available = [...recipients.options].some(option => option.value);
            const failed = [...recipients.options].some(option => option.textContent.includes('خطا در دریافت'));
            empty.hidden = available;
            empty.append(make('p', '', failed ? 'فهرست دریافت‌کنندگان دریافت نشد. دوباره تلاش کنید.' : 'این واحد کاربر واجد شرایط برای دعوت ندارد. ابتدا اطلاعات مالکیت یا سکونت و شماره موبایل کاربر را بررسی کنید.'));
            const retryButton = make('button', 'crud-button crud-button--soft', 'دریافت دوباره کاربران'); retryButton.type = 'button';
            retryButton.addEventListener('click', retry, { signal: abort.signal }); empty.append(retryButton);
            fields.prepend(heading); fields.append(empty, summary);
            const close = $('#crudDrawerClose'), cancel = $('#crudCancelButton');
            close.textContent = 'بازگشت به دعوت‌ها'; close.setAttribute('aria-label', close.textContent);
            $('#crudDrawerTitle').textContent = 'دعوت جدید'; $('#crudDrawerEyebrow').textContent = unitLabel();
            $('#crudFormError').setAttribute('role', 'alert');
            let busy = false;
            const selected = () => [...recipients.selectedOptions].filter(option => option.value);
            const sync = () => {
                fieldLabels.forEach(label => label.hidden = false);
                summary.hidden = !selected().length; empty.hidden = available;
                heading.textContent = 'دریافت‌کننده و اعتبار دعوت';
                summary.replaceChildren();
                for (const [label, value] of [['واحد', unitLabel()], ['دریافت‌کننده', selected().map(option => option.textContent).join('، ')], ['روش ارسال', 'پیامک به شماره ثبت‌شده کاربر']]) summary.append(make('dt', '', label), make('dd', '', value));
                saveButton.disabled = busy || !available;
                saveButton.textContent = busy ? 'در حال ارسال…' : 'ارسال دعوت';
                close.disabled = cancel.disabled = busy;
                form.setAttribute('aria-busy', String(busy));
            };
            const validate = () => {
                const error = $('#crudFormError'); error.textContent = '';
                recipients.removeAttribute('aria-invalid'); hours.removeAttribute('aria-invalid');
                if (selected().length !== 1) {
                    error.textContent = 'یک دریافت‌کننده انتخاب کنید.';
                    recipients.setAttribute('aria-invalid', 'true');
                    recipients.setAttribute('aria-describedby', 'crudFormError');
                    recipients.focus(); return false;
                }
                if (!hours.value || !Number.isInteger(Number(hours.value)) || !hours.checkValidity()) {
                    error.textContent = 'اعتبار دعوت باید عددی صحیح بین ۱ تا ۷۲۰ ساعت باشد.';
                    hours.setAttribute('aria-invalid', 'true');
                    hours.setAttribute('aria-describedby', 'crudFormError');
                    hours.focus(); return false;
                }
                return true;
            };
            fields.addEventListener('change', sync, { signal: abort.signal });
            controller = {
                get busy() { return busy; },
                sync,
                setBusy(value) { busy = value; sync(); },
                prepareSubmit() {
                    if (busy || !available) return false;
                    return validate();
                },
                showErrors(errors) {
                    sync();
                    for (const field of [recipients, hours]) field.removeAttribute('aria-invalid');
                    const target = Object.keys(errors).some(key => key === 'user_id' || key === 'user_ids' || key.startsWith('user_ids.')) ? recipients : errors.expires_in_hours ? hours : null;
                    if (target) { target.setAttribute('aria-invalid', 'true'); target.setAttribute('aria-describedby', 'crudFormError'); target.focus(); } else heading.focus();
                },
                destroy() { abort.abort(); form.noValidate = originalValidation; close.disabled = cancel.disabled = false; form.removeAttribute('aria-busy'); controller = null; },
            };
            sync(); heading.focus();
        },
        prepareSubmit: () => controller?.prepareSubmit() ?? false,
        sync: () => controller?.sync(),
        setBusy: value => controller?.setBusy(value),
        showErrors: errors => controller?.showErrors(errors),
        destroy: () => controller?.destroy(),
    };
})();
