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
    };

    persona.addEventListener('change', sync);
    sync();
})();
