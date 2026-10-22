(() => {
    'use strict';

    const fieldSelector = [
        'input:not([type="hidden"])',
        'select',
        'textarea',
    ].join(',');

    const normalizeDigits = (value) => String(value)
        .replace(
            /[۰-۹٠-٩]/g,
            (digit) => {
                const persian = '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);

                if (persian >= 0) {
                    return String(persian);
                }

                return String(
                    '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)
                );
            }
        );

    const isSensitiveField = (field) => {
        const type = String(
            field.getAttribute('type') || ''
        ).toLowerCase();

        const name = String(
            field.getAttribute('name') || ''
        ).toLowerCase();

        return type === 'password'
            || type === 'file'
            || name === '_token'
            || name === 'token'
            || name === 'access_token'
            || name === 'refresh_token'
            || name === 'api_token'
            || name === 'api_key'
            || name === 'secret'
            || name === 'signature'
            || name === 'hash'
            || name === 'invitation_token'
            || name.includes('password')
            || name.endsWith('_token')
            || name.endsWith('_secret')
            || name.endsWith('_signature')
            || name.endsWith('_hash')
            || name.endsWith('_api_key');
    };

    const normalizeFieldDigits = (field) => {
        if (
            field.tagName === 'SELECT'
            || isSensitiveField(field)
            || typeof field.value !== 'string'
        ) {
            return;
        }

        const normalized = normalizeDigits(field.value);

        if (normalized === field.value) {
            return;
        }

        const start = field.selectionStart;
        const end = field.selectionEnd;

        field.value = normalized;

        if (
            typeof field.setSelectionRange === 'function'
            && start !== null
            && end !== null
        ) {
            field.setSelectionRange(start, end);
        }
    };

    const setLoading = (element, loading = true) => {
        if (! element) {
            return;
        }

        element.dataset.buildinoLoading = loading
            ? 'true'
            : 'false';

        element.setAttribute(
            'aria-busy',
            loading ? 'true' : 'false'
        );
    };

    const toast = (message, icon = 'success') => {
        if (
            window.Swal
            && typeof window.Swal.fire === 'function'
        ) {
            return window.Swal.fire({
                toast: true,
                position: 'top-start',
                icon,
                title: message,
                showConfirmButton: false,
                timer: 3200,
                timerProgressBar: true,
            });
        }

        return Promise.resolve();
    };

    const confirm = async ({
        title = 'آیا مطمئن هستید؟',
        text = '',
        confirmButtonText = 'بله، ادامه می‌دهم',
        cancelButtonText = 'انصراف',
        icon = 'warning',
    } = {}) => {
        if (
            window.Swal
            && typeof window.Swal.fire === 'function'
        ) {
            const result = await window.Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText,
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'buildino-swal',
                },
            });

            return result.isConfirmed;
        }

        return window.confirm(
            [title, text].filter(Boolean).join('\n')
        );
    };

    const markReady = () => {
        document.documentElement.classList.add(
            'buildino-ready'
        );
    };

    const isLtrField = (field) => {
        const type = String(
            field.getAttribute('type') || ''
        ).toLowerCase();

        const name = String(
            field.getAttribute('name') || ''
        ).toLowerCase();

        return [
            'email',
            'tel',
            'url',
            'number',
            'date',
            'time',
            'datetime-local',
            'password',
        ].includes(type)
            || /mobile|phone|email|code|iban|card|url|website|postal/.test(name);
    };

    const clearInvalidState = (field) => {
        field.removeAttribute('aria-invalid');
        field.classList.remove('is-invalid');

        field.closest(
            '.auth-field, .portal-field, .crud-field, .ui-field, label'
        )?.classList.remove('has-error');
    };

    const markInvalidState = (field) => {
        field.setAttribute('aria-invalid', 'true');
        field.classList.add('is-invalid');

        field.closest(
            '.auth-field, .portal-field, .crud-field, .ui-field, label'
        )?.classList.add('has-error');
    };

    const enhanceField = (field) => {
        if (field.dataset.buildinoEnhanced === 'true') {
            return;
        }

        field.dataset.buildinoEnhanced = 'true';

        if (field.required) {
            field.setAttribute('aria-required', 'true');
        }

        if (
            ! field.hasAttribute('dir')
            && isLtrField(field)
        ) {
            field.setAttribute('dir', 'ltr');
        }

        field.addEventListener(
            'input',
            () => {
                normalizeFieldDigits(field);

                if (field.checkValidity()) {
                    clearInvalidState(field);
                }
            }
        );

        field.addEventListener(
            'change',
            () => {
                if (field.checkValidity()) {
                    clearInvalidState(field);
                }
            }
        );
    };

    const enhanceFeedback = (root = document) => {
        root
            .querySelectorAll(
                '.alert--success, .subscription-alert, .otp-login-status, [data-ui-status]'
            )
            .forEach((element) => {
                if (! element.hasAttribute('role')) {
                    element.setAttribute('role', 'status');
                }

                element.setAttribute('aria-live', 'polite');
            });

        root
            .querySelectorAll(
                '.alert--danger, .subscription-errors, .crud-form-error, .ui-field__error, .invalid-feedback, [data-ui-error]'
            )
            .forEach((element) => {
                if (! element.hasAttribute('role')) {
                    element.setAttribute('role', 'alert');
                }

                element.setAttribute('aria-live', 'assertive');
            });
    };

    const enhanceForms = (root = document) => {
        root
            .querySelectorAll(fieldSelector)
            .forEach(enhanceField);

        enhanceFeedback(root);
    };

    window.BuildinoUI = Object.freeze({
        confirm,
        enhanceForms,
        normalizeDigits,
        setLoading,
        toast,
    });

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            () => {
                markReady();
                enhanceForms();
            },
            { once: true }
        );
    } else {
        markReady();
        enhanceForms();
    }

    document.addEventListener(
        'invalid',
        (event) => {
            const field = event.target;

            if (! field.matches?.(fieldSelector)) {
                return;
            }

            markInvalidState(field);

            if (
                field.form?.querySelector(':invalid')
                === field
            ) {
                window.requestAnimationFrame(() => {
                    field.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });
                });
            }
        },
        true
    );

    document.addEventListener('submit', (event) => {
        const form = event.target.closest(
            'form[data-buildino-submit]'
        );

        if (! form) {
            return;
        }

        if (! form.checkValidity()) {
            return;
        }

        const submitter = event.submitter
            ?? form.querySelector('[type="submit"]');

        setLoading(submitter, true);
        form.setAttribute('aria-busy', 'true');
    });

    document.addEventListener(
        'buildino:form-mounted',
        (event) => {
            enhanceForms(event.target || document);
        }
    );

    window.addEventListener('pageshow', () => {
        document
            .querySelectorAll(
                '[data-buildino-loading="true"]'
            )
            .forEach((element) => {
                setLoading(element, false);
            });

        document
            .querySelectorAll('form[aria-busy="true"]')
            .forEach((form) => {
                form.removeAttribute('aria-busy');
            });
    });
})();
