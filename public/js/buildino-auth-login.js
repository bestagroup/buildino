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
            };

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    activate(button.dataset.authMethod);
                });
            });

            scope.querySelectorAll('[data-otp-change]')
                .forEach((button) => {
                    button.addEventListener('click', () => {
                        const verification = scope.querySelector(
                            '[data-otp-verification]'
                        );
                        const requestForm = scope.querySelector(
                            '[data-otp-request-form]'
                        );

                        if (verification) {
                            verification.hidden = true;
                        }

                        if (requestForm) {
                            requestForm.hidden = false;
                            requestForm.querySelector(
                                'input[name="mobile"]'
                            )?.focus();
                        }
                    });
                });

            activate(switcher.dataset.activeMethod);
        });
})();
