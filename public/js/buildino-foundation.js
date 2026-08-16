(() => {
    'use strict';

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
        confirmButtonText = 'بله، ادامه بده',
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

    window.BuildinoUI = Object.freeze({
        confirm,
        setLoading,
        toast,
    });

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            markReady,
            { once: true }
        );
    } else {
        markReady();
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest(
            'form[data-buildino-submit]'
        );

        if (! form) {
            return;
        }

        const submitter = event.submitter
            ?? form.querySelector('[type="submit"]');

        setLoading(submitter, true);
    });

    window.addEventListener('pageshow', () => {
        document
            .querySelectorAll(
                '[data-buildino-loading="true"]'
            )
            .forEach((element) => {
                setLoading(element, false);
            });
    });
})();
