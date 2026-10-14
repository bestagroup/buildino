(() => {
    'use strict';

    document.querySelectorAll('[data-auth-switch]')
        .forEach((switcher) => {
            const scope = switcher.closest(
                '.login-card, .portal-login-card'
            ) || document;
            const buttons = [
                ...switcher.querySelectorAll(
                    '[data-auth-method]'
                ),
            ];
            const panels = [
                ...scope.querySelectorAll(
                    '[data-auth-panel]'
                ),
            ];
            const verification = scope.querySelector('[data-otp-verification]');
            const requestForm = scope.querySelector('[data-otp-request-form]');
            const otpStatus = scope.querySelector('[data-otp-status]');

            const showOtpStage = (verify, focus = false) => {
                const showVerification = Boolean(verify && verification);
                if (verification) verification.hidden = !showVerification;
                if (requestForm) requestForm.hidden = showVerification;
                if (otpStatus) otpStatus.hidden = !showVerification;
                if (focus) {
                    (showVerification ? verification : requestForm)
                        ?.querySelector('input:not([type="hidden"])')?.focus();
                }
            };

            const activate = (method) => {
                const selected = method === 'otp'
                    ? 'otp'
                    : 'password';

                switcher.dataset.activeMethod = selected;

                buttons.forEach((button) => {
                    const active =
                        button.dataset.authMethod === selected;

                    button.classList.toggle(
                        'is-active',
                        active
                    );
                    button.setAttribute(
                        'aria-selected',
                        active ? 'true' : 'false'
                    );
                });

                panels.forEach((panel) => {
                    panel.hidden =
                        panel.dataset.authPanel !== selected;
                });
                if (selected === 'otp') showOtpStage(verification && !verification.hidden, true);
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    activate(button.dataset.authMethod);
                });
            });

            scope.querySelectorAll('[data-otp-change]')
                .forEach((button) => {
                    button.addEventListener('click', () => {
                        showOtpStage(false, true);
                    });
                });

            showOtpStage(verification && !verification.hidden);
            activate(switcher.dataset.activeMethod);
        });
})();
