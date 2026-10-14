(() => {
    'use strict';
    const config = window.BuildinoAuthUi || {};
    const normalizeDigits = (value) => String(value).replace(/[۰-۹٠-٩]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
    const tokenKey = 'buildino.pending-invitation';
    const token = new URL(window.location.href).searchParams.get('invitation_token');
    const saveInvitation = (value) => {
        if (typeof value !== 'string' || !/^[a-zA-Z0-9]{32,255}$/.test(value)) return false;
        try { sessionStorage.setItem(tokenKey, JSON.stringify({ token: value })); return true; } catch { return false; }
    };
    if (token) saveInvitation(token);
    const registration = document.querySelector('[data-registration-form]');
    if (registration) {
        const input = registration.querySelector('[name="invitation_token"]');
        const inviteSection = registration.querySelector('[data-registration-section="resident"]');
        if (inviteSection) {
            const existing = document.createElement('a'); existing.className = 'auth-back-link'; existing.textContent = 'حساب دارید؟ وارد شوید و دعوت را ادامه دهید';
            const update = () => {
                const url = new URL(config.portalLoginUrl, window.location.origin);
                if (/^[a-zA-Z0-9]{32,255}$/.test(input.value)) url.searchParams.set('invitation_token', input.value);
                existing.href = url.pathname + url.search;
            };
            input.addEventListener('input', update); update();
            existing.addEventListener('click', (event) => {
                if (input.value && !saveInvitation(input.value)) {
                    const note = document.createElement('p'); note.className = 'core-auth-helper'; note.textContent = 'پس از ورود، لینک اصلی دعوت را دوباره باز کنید.'; inviteSection.append(note);
                    event.preventDefault(); window.setTimeout(() => window.location.assign(existing.href), 1500);
                }
            });
            inviteSection.append(existing);
        }
        for (const section of registration.querySelectorAll('[data-registration-section="management"]')) {
            if (registration.querySelector('[data-registration-step]')) continue;
            const optional = [...section.querySelectorAll('label')].filter((label) => ['address', 'postal_code'].includes(label.querySelector('[name]')?.name));
            if (optional.length) {
                const details = document.createElement('details'); details.className = 'core-form-details';
                const summary = document.createElement('summary'); summary.textContent = 'نشانی و کد پستی (اختیاری)';
                const grid = document.createElement('div'); details.append(summary, grid); section.append(details); optional.forEach((label) => grid.append(label));
            }
        }
        registration.addEventListener('submit', () => { if (input?.value) { try { sessionStorage.removeItem(tokenKey); } catch {} } });
    }
    const passwordIcons = {
        show: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        hide: '<path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.1 4M6.3 6.3A19 19 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.7-1.4"/>',
    };
    for (const input of document.querySelectorAll('input[type="password"]')) {
        const wrapper = input.parentElement.classList.contains('auth-input') ? input.parentElement : document.createElement('div');
        if (wrapper !== input.parentElement) { input.before(wrapper); wrapper.append(input); }
        wrapper.classList.add('core-auth-password');
        wrapper.dir = 'rtl';
        const button = document.createElement('button'); button.type = 'button'; button.className = 'core-auth-toggle';
        input.id ||= `core-password-${input.name}`; button.setAttribute('aria-controls', input.id);
        const updateToggle = () => {
            const visible = input.type === 'text';
            const label = visible ? 'پنهان‌کردن رمز' : 'نمایش رمز';
            button.setAttribute('aria-label', label); button.title = label;
            button.setAttribute('aria-pressed', String(visible));
            button.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${passwordIcons[visible ? 'hide' : 'show']}</svg>`;
        };
        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password'; updateToggle();
        });
        updateToggle(); wrapper.append(button);
    }
    for (const input of document.querySelectorAll('input[name="code"]')) {
        input.inputMode = 'numeric'; input.autocomplete = 'one-time-code'; input.dir = 'ltr';
        input.maxLength = Number(config.otpDigits) || 6;
        input.pattern = `[0-9]{${input.maxLength}}`;
        input.setAttribute('aria-label', `کد تأیید ${input.maxLength} رقمی`);
        input.addEventListener('input', () => { input.value = normalizeDigits(input.value).replace(/\D/g, '').slice(0, input.maxLength); });
        input.value = normalizeDigits(input.value);
    }
    for (const [name, messages] of Object.entries(config.errors || {})) {
        const inputs = [...document.querySelectorAll(`[name="${CSS.escape(name)}"], [data-registration-error-name="${CSS.escape(name)}"]`)];
        for (const input of inputs) {
            input.setAttribute('aria-invalid', 'true');
            const field = input.closest('label'); if (!field) continue;
            const details = input.closest('details'); if (details) details.open = true;
            const error = document.createElement('small'); error.className = 'core-field-error'; error.id = `auth-error-${name}-${inputs.indexOf(input)}`; error.setAttribute('role', 'alert'); error.textContent = messages[0];
            input.setAttribute('aria-describedby', error.id); field.append(error);
        }
    }
    const firstError = [...document.querySelectorAll('[aria-invalid="true"]')].find((input) => input.getClientRects().length);
    firstError?.focus();
    for (const switcher of document.querySelectorAll('[data-auth-switch]')) {
        const tabs = [...switcher.querySelectorAll('[role="tab"]')];
        const scope = switcher.closest('.login-card, .portal-login-card');
        const update = () => tabs.forEach((tab, index) => {
            tab.id ||= `auth-tab-${index}`; tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
            const panel = scope.querySelector(`[data-auth-panel="${tab.dataset.authMethod}"]`);
            if (panel) { panel.id ||= `auth-panel-${index}`; panel.setAttribute('role', 'tabpanel'); panel.setAttribute('aria-labelledby', tab.id); tab.setAttribute('aria-controls', panel.id); }
        });
        switcher.addEventListener('click', update); update();
        switcher.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const index = tabs.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowLeft' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[next].click(); tabs[next].focus();
        });
    }
    const timerKey = `buildino.otp-ui.${window.location.pathname}`;
    let lastSent = 0;
    try {
        lastSent = Number(sessionStorage.getItem(timerKey)) || 0;
        if (config.otpSent) { lastSent = Date.now(); sessionStorage.setItem(timerKey, String(lastSent)); }
    } catch { if (config.otpSent) lastSent = Date.now(); }
    const resendForms = [...document.querySelectorAll('form')].filter((form) => form.action.endsWith('/register/otp/resend') || (form.querySelector('input[type="hidden"][name="mobile"]') && /\/login\/otp\/request$/.test(form.action)));
    if (resendForms.length && lastSent) {
        const labels = resendForms.map((form) => { const label = document.createElement('span'); label.className = 'core-otp-timer'; form.append(label); return label; });
        const tick = () => {
            const remaining = Math.max(0, Math.ceil((lastSent + (Number(config.resendAfter) || 60) * 1000 - Date.now()) / 1000));
            resendForms.forEach((form, index) => {
                const button = form.querySelector('button[type="submit"]');
                if (form.dataset.buildinoLoading !== 'true' && button) button.disabled = remaining > 0;
                labels[index].textContent = remaining ? `ارسال مجدد تا ${remaining.toLocaleString('fa-IR')} ثانیه دیگر` : 'می‌توانید کد جدید درخواست کنید.';
            });
            return remaining;
        };
        if (tick()) { const interval = window.setInterval(() => { if (!tick()) window.clearInterval(interval); }, 1000); }
    }
    if (token && document.querySelector('.portal-login-card')) {
        const note = document.createElement('p'); note.className = 'core-auth-helper'; note.textContent = 'با حساب دریافت‌کنندهٔ دعوت وارد شوید. اگر هنوز دسترسی پرتال ندارید، از مدیر ساختمان بخواهید دسترسی حساب شما را بررسی کند.';
        document.querySelector('.portal-login-card').append(note);
    }
})();
