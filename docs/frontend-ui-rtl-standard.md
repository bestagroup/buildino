# Buildino Frontend UI Standard

This document is the implementation baseline for all current and future web UI in Buildino.

## 1. UI framework strategy

Buildino uses **Bootstrap 5.3 RTL** as the primary component and utility foundation for the management and portal applications.

The project intentionally does **not** mix Materialize with Bootstrap. Both frameworks ship global resets, form styles, grid rules and component conventions; loading them together would create unnecessary CSS conflicts, bundle growth and inconsistent interaction patterns.

Existing complementary libraries remain:

- Bootstrap 5.3.8 RTL
- DataTables 2 + Bootstrap 5 adapter
- Select2
- SweetAlert2
- Jalali Datepicker + jalaali-js
- Vue 3 + Inertia for progressively migrated management resources
- Tailwind CSS only for the public landing surface

## 2. Persian typography

The product font is **IRANSans / IRANSansX**.

Licensed font binaries must be deployed to:

- `public/fonts/iransans/IRANSansX-Regular.woff2`
- `public/fonts/iransans/IRANSansX-Medium.woff2`
- `public/fonts/iransans/IRANSansX-Bold.woff2`

The product keeps a Persian-capable system fallback so an unavailable licensed font never blocks rendering.

## 3. RTL rules

All product HTML is `lang="fa"` and `dir="rtl"`.

Use logical CSS properties whenever possible:

- `margin-inline-*`
- `padding-inline-*`
- `border-inline-*`
- `inset-inline-*`

Fields that contain inherently LTR data remain LTR:

- mobile / phone
- email
- IBAN / card numbers
- URLs
- passwords
- numeric identifiers
- date/time values used by native or enhanced controls

## 4. Form UX baseline

Every form must support:

- default
- hover
- focus
- invalid
- disabled
- loading / submitting
- success feedback
- server error feedback
- empty and no-result states where applicable

Required controls expose `aria-required`. Invalid controls expose `aria-invalid`. Error feedback uses assertive live regions and success feedback uses polite live regions.

Buttons must prevent repeated submission while a request is active.

## 5. Responsive baseline

Primary breakpoints follow Bootstrap conventions.

Mobile requirements:

- controls have touch-friendly height
- form grids collapse to one column
- modal content becomes edge-to-edge on narrow phones
- tables preserve horizontal scrolling
- drawers use the full safe viewport
- action groups wrap instead of overflowing
- safe-area insets are respected where overlays touch screen edges

## 6. Accessibility

The UI includes:

- visible keyboard focus
- skip links for management and portal shells
- focus trapping in drawers
- Escape handling for dialogs
- reduced-motion support
- semantic dialog roles
- accessible form feedback
- RTL-aware keyboard and reading order

## 7. Typography readability

Primary management, portal and CRUD styles must not use text below 11px. Functional copy should normally be 12–14px or larger depending on hierarchy.

## 8. Regression coverage

`Tests\Feature\Web\UiFoundationWebTest` guards:

- pinned UI dependencies
- Bootstrap RTL
- IRANSans configuration
- shared design-system loading
- RTL markup
- accessibility foundation
- searchable selects
- Jalali date inputs
- minimum readable font sizes

## 9. Source of truth

Shared UI rules live primarily in:

- `public/css/buildino-fonts.css`
- `public/css/buildino-foundation.css`
- `public/css/buildino-design-system.css`
- `public/js/buildino-foundation.js`

Feature CSS should use the shared tokens rather than creating a separate visual system.
