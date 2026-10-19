(() => {
    'use strict';

    const form = document.querySelector(
        '[data-registration-form]'
    );

    if (! form) {
        return;
    }

    const persona = form.querySelector(
        '[data-registration-persona]'
    );
    const description = form.querySelector(
        '[data-persona-description]'
    );
    const sections = [
        ...form.querySelectorAll(
            '[data-registration-section]'
        ),
    ];
    const steps = [...form.querySelectorAll('[data-registration-step]')];
    const progress = document.querySelector('[data-registration-progress]');
    const progressButtons = [...document.querySelectorAll('[data-registration-go]')];
    const next = form.querySelector('[data-registration-next]');
    const previous = form.querySelector('[data-registration-previous]');
    const submit = form.querySelector('[data-registration-submit]');
    const provinceSelect = form.querySelector('[data-registration-province]');
    const citySelect = form.querySelector('[data-registration-city]');
    const cityHint = form.querySelector('[data-registration-city-hint]');
    const cityGroups = citySelect ? [...citySelect.querySelectorAll('optgroup')] : [];
    let currentProvince = provinceSelect?.value;
    let currentStep = 0;

    const syncCities = () => {
        if (!provinceSelect || !citySelect) return;
        const changedProvince = currentProvince !== provinceSelect.value;
        const retainedCity = changedProvince ? '' : citySelect.value;
        currentProvince = provinceSelect.value;
        const group = cityGroups.find((item) => item.dataset.provinceId === currentProvince);
        citySelect.replaceChildren(new Option(group ? 'شهر را انتخاب کنید' : 'ابتدا استان را انتخاب کنید', ''));
        if (group) citySelect.append(group);
        citySelect.value = retainedCity;
        if (citySelect.selectedIndex < 0) citySelect.value = '';
        citySelect.disabled = provinceSelect.disabled || !group;
        const placeholder = group ? 'شهر را انتخاب کنید' : 'ابتدا استان را انتخاب کنید';
        if (citySelect.dataset.placeholder !== placeholder) window.BuildinoSelect2?.destroy(citySelect);
        citySelect.dataset.placeholder = placeholder;
        cityHint.textContent = group ? `شهرهای استان ${group.label}؛ برای پیدا کردن شهر، نام آن را جست‌وجو کنید.` : 'ابتدا استان را انتخاب کنید؛ سپس شهر را جست‌وجو کنید.';
        if (changedProvince) citySelect.dispatchEvent(new Event('input', { bubbles: true }));
        window.BuildinoSelect2?.refresh(citySelect);
    };

    const contextCopy = {
        management: ['فضای کار و نشانی', 'فضای کار اولیه و نشانی', 'اطلاعات مجتمع و ساختمان اولیه را وارد کنید؛ نشانی و کد پستی اختیاری‌اند.'],
        resident: ['دعوت واحد', 'اتصال به واحد شما', 'کد دعوت مدیر ساختمان را وارد کنید و قوانین استفاده را بپذیرید.'],
        provider: ['تکمیل حساب', 'آماده ورود به پرتال خدمات', 'پس از پذیرش قوانین و تأیید موبایل، وارد پرتال خدمات می‌شوید.'],
    };

    const setSectionState = (section, enabled) => {
        section.hidden = ! enabled;

        section.querySelectorAll(
            'input, select, textarea, button'
        ).forEach((control) => {
            control.disabled = ! enabled;
        });
    };

    const sync = () => {
        const option = persona.options[
            persona.selectedIndex
        ];
        const kind = option?.dataset.kind
            || 'management';

        sections.forEach((section) => {
            setSectionState(
                section,
                section.dataset.registrationSection
                    === kind
            );
        });

        if (description) {
            description.textContent =
                option?.dataset.description || '';
        }

        const management = kind === 'management';
        const resident = kind === 'resident';

        [
            'complex_title',
            'building_title',
            'province',
            'city',
            'province_id',
            'city_id',
        ].forEach((name) => {
            const field = form.elements.namedItem(name);

            if (field) {
                field.required = management;
            }
        });

        const invitation = form.elements.namedItem(
            'invitation_token'
        );

        if (invitation) {
            invitation.required = resident;
        }
        const copy = contextCopy[kind] || contextCopy.management;
        const label = document.querySelector('[data-registration-context-label]');
        const heading = form.querySelector('[data-registration-context-heading]');
        const hint = form.querySelector('[data-registration-context-description]');
        if (label) label.textContent = copy[0];
        if (heading) heading.textContent = copy[1];
        if (hint) hint.textContent = copy[2];
        syncCities();
    };

    persona.addEventListener('change', sync);
    provinceSelect?.addEventListener('change', syncCities);
    sync();

    if (steps.length !== 4 || !next || !previous || !submit) return;

    const fieldsIn = (step) => [...step.querySelectorAll('input, select, textarea')]
        .filter((field) => !field.disabled && field.type !== 'hidden');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const completionHint = form.querySelector('[data-registration-completion]');
    let stageAnimation;

    const focusField = (field) => {
        const select2 = field.matches('select') && field.parentElement.querySelector('.select2-selection');
        (select2 || field).focus();
    };

    const showStep = (index, focus = true) => {
        const lastStep = currentStep;
        stageAnimation?.cancel();
        currentStep = index;
        steps.forEach((step, position) => { step.hidden = position !== index; });
        previous.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        submit.hidden = index !== steps.length - 1;
        progressButtons.forEach((button, position) => {
            button.disabled = position > index;
            button.parentElement.classList.toggle('is-complete', position < index);
            if (position === index) button.parentElement.setAttribute('aria-current', 'step');
            else button.parentElement.removeAttribute('aria-current');
        });
        document.querySelector('[data-registration-current-step]').textContent = (index + 1).toLocaleString('fa-IR');
        const bar = document.querySelector('[data-registration-progress-bar]');
        bar.setAttribute('aria-valuenow', String(index + 1));
        bar.setAttribute('aria-valuetext', `مرحله ${(index + 1).toLocaleString('fa-IR')} از ۴: ${steps[index].querySelector('h3').textContent}`);
        bar.style.setProperty('--registration-progress', `${((index + 1) / steps.length) * 100}%`);
        bar.style.setProperty('--registration-progress-scale', String((index + 1) / steps.length));
        updateCompletion();
        if (focus && index !== lastStep && !reducedMotion.matches && steps[index].animate) {
            stageAnimation = steps[index].animate([
                { opacity: 0, transform: `translateX(${index > lastStep ? -14 : 14}px)` },
                { opacity: 1, transform: 'translateX(0)' },
            ], { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' });
        }
        if (focus) {
            steps[index].querySelector('h3').focus({ preventScroll: true });
            const bounds = steps[index].querySelector('h3').getBoundingClientRect();
            if (bounds.top < 0 || bounds.bottom > window.innerHeight) {
                progress.scrollIntoView({ block: 'start', behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            }
        }
    };

    const digits = (value) => value.replace(/[۰-۹٠-٩]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));
    const mobile = form.elements.namedItem('mobile');
    const password = form.elements.namedItem('password');
    const confirmation = form.elements.namedItem('password_confirmation');

    // Read validity without checkValidity(): typing must not fire native invalid events.
    const isComplete = (field) => {
        if (field.type === 'checkbox') return field.checked;
        const value = field.value.trim();
        if (!value) return !field.required;
        if (field === mobile) {
            const normalized = digits(value).replace(/[\s\-()]+/g, '').replace(/^(?:\+98|0098|98)(?=9\d{9}$)/, '0');
            return /^09\d{9}$/.test(normalized);
        }
        if (field === password) return field.value.length >= 8 && /\p{L}/u.test(field.value) && /\p{N}/u.test(field.value);
        if (field === confirmation) return field.value === password.value && isComplete(password);
        return field.validity.valid;
    };

    const updateCompletion = () => {
        const fields = fieldsIn(steps[currentStep]);
        const required = fields.filter((field) => field.required);
        const complete = required.filter(isComplete).length;
        const ready = fields.every(isComplete);
        const fraction = Math.min(ready ? 1 : .95, required.length ? complete / required.length : 1);
        for (const button of [next, submit]) {
            button.style.setProperty('--registration-step-completion', String(fraction));
            button.dataset.stepReady = String(ready);
        }
        if (completionHint) {
            const message = ready
                ? (currentStep === steps.length - 1 ? 'آماده ارسال کد تأیید' : 'این مرحله تکمیل است؛ ادامه دهید')
                : `${complete.toLocaleString('fa-IR')} از ${required.length.toLocaleString('fa-IR')} مورد ضروری تکمیل شده`;
            if (completionHint.textContent !== message) completionHint.textContent = message;
            completionHint.dataset.ready = String(ready);
        }
    };

    const validateField = (field) => {
        field.setCustomValidity('');
        if (field === mobile) {
            field.value = digits(field.value.trim()).replace(/[\s\-()]+/g, '').replace(/^(?:\+98|0098|98)(?=9\d{9}$)/, '0');
            if (field.value && !/^09\d{9}$/.test(field.value)) field.setCustomValidity('شماره موبایل باید با 09 شروع شود و ۱۱ رقم باشد.');
        } else if (field === password && field.value && (field.value.length < 8 || !/\p{L}/u.test(field.value) || !/\p{N}/u.test(field.value))) {
            field.setCustomValidity('رمز عبور باید حداقل ۸ کاراکتر و شامل حرف و عدد باشد.');
        } else if (field === confirmation && field.value && field.value !== password.value) {
            field.setCustomValidity('تکرار رمز عبور با رمز عبور یکسان نیست.');
        } else if (field.required && field.type !== 'checkbox' && !field.value.trim()) {
            field.setCustomValidity('این فیلد را تکمیل کنید.');
        }
        let error = field.closest('label')?.querySelector('[data-registration-validation]');
        if (field.checkValidity()) {
            error?.remove();
            field.classList.remove('is-invalid');
            field.closest('label')?.classList.remove('has-error');
            return true;
        }
        if (!error) {
            error = document.createElement('small');
            error.className = 'core-field-error';
            error.dataset.registrationValidation = '';
            error.id = `registration-error-${field.name}`;
            error.setAttribute('role', 'alert');
            field.closest('label')?.append(error);
        }
        error.textContent = field.validity.valueMissing
            ? (field.type === 'checkbox' ? 'پذیرش قوانین و حریم خصوصی الزامی است.' : 'این فیلد را تکمیل کنید.')
            : field.validity.typeMismatch ? 'ایمیل واردشده معتبر نیست.' : field.validationMessage;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', error.id);
        return false;
    };

    const validateStep = (index) => {
        const invalid = fieldsIn(steps[index]).filter((field) => !validateField(field));
        if (!invalid.length) return true;
        showStep(index, false);
        focusField(invalid[0]);
        return false;
    };

    const advance = () => {
        if (validateStep(currentStep)) showStep(currentStep + 1);
        else if (!reducedMotion.matches && next.animate) {
            next.animate([
                { transform: 'translateX(0)' }, { transform: 'translateX(3px)' },
                { transform: 'translateX(-3px)' }, { transform: 'translateX(0)' },
            ], { duration: 180, easing: 'ease-out' });
        }
    };
    next.addEventListener('click', advance);
    previous.addEventListener('click', () => showStep(currentStep - 1));
    progressButtons.forEach((button, index) => button.addEventListener('click', () => { if (index < currentStep) showStep(index); }));

    const clearFeedback = (event) => {
        const field = event.target;
        if (!field.matches('input, select, textarea')) return;
        field.setCustomValidity('');
        field.closest('label')?.querySelectorAll('.core-field-error').forEach((error) => error.remove());
        field.classList.remove('is-invalid');
        field.closest('label')?.classList.remove('has-error');
        field.removeAttribute('aria-invalid');
        if (field === citySelect) field.setAttribute('aria-describedby', 'registration-city-hint');
        else field.removeAttribute('aria-describedby');
        if (field === password) confirmation.setCustomValidity('');
    };
    form.addEventListener('input', clearFeedback);
    form.addEventListener('change', clearFeedback);
    form.addEventListener('input', updateCompletion);
    form.addEventListener('change', updateCompletion);
    // Password managers may autofill without dispatching an input event.
    form.addEventListener('focusin', updateCompletion);
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches) stageAnimation?.cancel();
    });

    form.addEventListener('submit', (event) => {
        if (currentStep < steps.length - 1) {
            event.preventDefault();
            event.stopImmediatePropagation();
            advance();
            return;
        }
        for (let index = 0; index < steps.length; index++) {
            if (!validateStep(index)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
        }
    }, true);

    // Controls in previous stages stay enabled so their values reach Laravel.
    // Only irrelevant persona fields are disabled by sync().
    form.noValidate = true;
    form.dataset.registrationWizard = 'ready';
    const errors = window.BuildinoAuthUi?.errors || {};
    const errorStep = steps.findIndex((step) => [...step.querySelectorAll('[name]')].some((field) => Object.hasOwn(errors, field.name) || Object.hasOwn(errors, field.dataset.registrationErrorName || '')));
    showStep(errorStep >= 0 ? errorStep : 0, false);
    window.addEventListener('pageshow', () => { sync(); showStep(currentStep, false); });
})();
