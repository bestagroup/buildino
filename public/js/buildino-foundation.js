(() => {
    'use strict';

    const iconFor = (tone) => ({
        danger: 'error',
        error: 'error',
        warning: 'warning',
        info: 'info',
        success: 'success',
    }[tone] || 'success');

    const setLoading = (element, loading = true) => {
        if (! element) {
            return;
        }

        element.dataset.buildinoLoading = loading ? 'true' : 'false';
        element.setAttribute('aria-busy', loading ? 'true' : 'false');

        if ('disabled' in element) {
            if (loading) {
                if (! element.dataset.buildinoWasDisabled) {
                    element.dataset.buildinoWasDisabled = element.disabled ? '1' : '0';
                }
                element.disabled = true;
            } else {
                element.disabled = element.dataset.buildinoWasDisabled === '1';
                delete element.dataset.buildinoWasDisabled;
            }
        }
    };

    const toast = (message, tone = 'success') => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                toast: true,
                position: 'top-start',
                icon: iconFor(tone),
                title: String(message ?? ''),
                showConfirmButton: false,
                timer: 3400,
                timerProgressBar: true,
                customClass: {
                    popup: 'buildino-swal-toast',
                },
            });
        }

        if (typeof window.alert === 'function') {
            window.alert(String(message ?? ''));
        }

        return Promise.resolve();
    };

    const confirm = async ({
        title = 'آیا مطمئن هستید؟',
        text = '',
        confirmButtonText = 'بله، ادامه بده',
        cancelButtonText = 'انصراف',
        icon = 'warning',
    } = {}) => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            const result = await window.Swal.fire({
                title,
                text,
                icon: iconFor(icon),
                showCancelButton: true,
                confirmButtonText,
                cancelButtonText,
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'buildino-swal-popup',
                    confirmButton: 'buildino-swal-confirm',
                    cancelButton: 'buildino-swal-cancel',
                    actions: 'buildino-swal-actions',
                },
            });

            return Boolean(result.isConfirmed);
        }

        return window.confirm([title, text].filter(Boolean).join('\n'));
    };

    const clearFieldError = (field) => {
        if (! field) {
            return;
        }

        field.classList.remove('is-invalid', 'buildino-invalid');
        field.removeAttribute('aria-invalid');

        const originalDescribedBy =
            field.dataset.buildinoOriginalDescribedBy;

        if (originalDescribedBy !== undefined) {
            if (originalDescribedBy) {
                field.setAttribute('aria-describedby', originalDescribedBy);
            } else {
                field.removeAttribute('aria-describedby');
            }

            delete field.dataset.buildinoOriginalDescribedBy;
        }

        field.closest('.crud-field, .portal-field, .form-field, .mb-3, label')
            ?.querySelectorAll('[data-buildino-field-error]')
            .forEach((node) => node.remove());
    };

    const clearValidationErrors = (root) => {
        if (! root) {
            return;
        }

        root.querySelectorAll('.is-invalid, .buildino-invalid').forEach(clearFieldError);
        root.querySelectorAll('[data-buildino-field-error]').forEach((node) => node.remove());
    };

    const fieldForError = (root, name) => {
        if (! root || ! name) {
            return null;
        }

        const path = String(name);
        const segments = path.split('.');
        const candidates = [
            path,
            path.replace(/\.\d+(?=\.|$)/g, '[]'),
            segments[0],
            segments[segments.length - 1],
        ];

        for (const candidate of candidates) {
            const field = root.querySelector(`[name="${CSS.escape(candidate)}"]`);
            if (field) {
                return field;
            }
        }

        return null;
    };

    const applyValidationErrors = (root, errors = {}) => {
        if (! root || ! errors || typeof errors !== 'object') {
            return 0;
        }

        clearValidationErrors(root);
        let count = 0;
        let firstInvalid = null;

        Object.entries(errors).forEach(([name, messages], index) => {
            const field = fieldForError(root, name);
            if (! field) {
                return;
            }

            const message = Array.isArray(messages) ? messages[0] : messages;
            if (! message) {
                return;
            }

            count += 1;
            firstInvalid ||= field;
            field.classList.add('is-invalid', 'buildino-invalid');
            field.setAttribute('aria-invalid', 'true');

            const errorId = `buildino-error-${Date.now()}-${index}`;
            field.dataset.buildinoOriginalDescribedBy =
                field.getAttribute('aria-describedby') || '';
            field.setAttribute('aria-describedby', errorId);

            const error = document.createElement('small');
            error.id = errorId;
            error.className = 'buildino-field-error';
            error.dataset.buildinoFieldError = '1';
            error.textContent = String(message);

            const container = field.closest('.crud-field, .portal-field, .form-field, .mb-3, label');
            (container || field.parentElement)?.appendChild(error);
        });

        if (firstInvalid) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return count;
    };

    const markReady = () => {
        document.documentElement.classList.add('buildino-ready');
    };

    const previous = window.BuildinoUI && typeof window.BuildinoUI === 'object'
        ? window.BuildinoUI
        : {};

    window.BuildinoUI = Object.freeze({
        ...previous,
        confirm,
        setLoading,
        toast,
        clearValidationErrors,
        applyValidationErrors,
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', markReady, { once: true });
    } else {
        markReady();
    }

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) {
            return;
        }

        const form = event.target.closest('form');
        if (! form || form.matches('[data-buildino-no-loading]')) {
            return;
        }

        const submitter = event.submitter ?? form.querySelector('[type="submit"]');
        setLoading(submitter, true);
    });

    document.addEventListener('input', (event) => {
        const field = event.target.closest('input, select, textarea');
        if (field?.matches('.is-invalid, .buildino-invalid')) {
            clearFieldError(field);
        }
    });

    document.addEventListener('change', (event) => {
        const field = event.target.closest('select, input[type="checkbox"], input[type="radio"]');
        if (field?.matches('.is-invalid, .buildino-invalid')) {
            clearFieldError(field);
        }
    });

    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (! field?.matches?.('input, select, textarea')) {
            return;
        }

        field.classList.add('is-invalid', 'buildino-invalid');
        field.setAttribute('aria-invalid', 'true');
    }, true);

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-buildino-loading="true"]').forEach((element) => {
            setLoading(element, false);
        });
    });
})();
