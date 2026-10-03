(() => {
    'use strict';

    const fieldSelector = [
        'input:not([type="hidden"])',
        'select',
        'textarea',
    ].join(',');

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

    const enhanceForms = (root = document) => {
        root
            .querySelectorAll(fieldSelector)
            .forEach(enhanceField);
    };

    window.BuildinoUI = Object.freeze({
        confirm,
        enhanceForms,
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

            window.requestAnimationFrame(() => {
                field.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            });
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
