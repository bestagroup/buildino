# Buildino v3.10 — Authentication, Responsive & Cross-browser Visual QA

This phase keeps the Materialize/Pixinvent design system introduced in v3.7 and does not add another theme layer.

## Scope

- Management login, forgot-password and reset-password presentation.
- Resident/Provider login presentation.
- Mobile navigation focus, scroll lock and ARIA state synchronization.
- Dark/light theme synchronization with the operating-system preference when the user has no explicit saved preference.
- Safe-area and dynamic viewport handling for mobile browsers.
- DataTables/table horizontal overflow containment.
- Bootstrap modal viewport containment and mobile scrolling.
- Keyboard focus visibility and reduced-motion/high-contrast fallbacks.

## Non-goals

No Controller, Service, Model, Policy, Route, Migration, financial rule, authorization rule, form field name, form action or DataTables endpoint is changed by this phase.

## Browser strategy

The implementation uses progressively enhanced CSS. `100vh` precedes `100dvh`, a solid background precedes optional translucent effects, and older WebKit `MediaQueryList.addListener()` is supported as a fallback. Unsupported cosmetic capabilities therefore degrade visually instead of breaking layout or interaction.

Runtime browser automation is not claimed by the static validation report. Real Chrome/Edge/Firefox/Safari visual verification remains part of UAT.
