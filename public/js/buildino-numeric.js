(() => {
    'use strict';
    if (window.BuildinoNumeric) return;
    const normalize = value => String(value ?? '')
        .replace(/[۰-۹٠-٩]/g, digit => String(digit.charCodeAt(0) - (digit >= '۰' ? 1776 : 1632)))
        .replace(/[,٬\s]/g, '').replace(/٫/g, '.').replace(/−/g, '-');
    const format = value => {
        const raw = normalize(value);
        if (!/^-?\d*(?:\.\d*)?$/.test(raw)) return raw;
        const [integer, fraction] = raw.split('.');
        return integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (fraction === undefined ? '' : '.' + fraction);
    };
    const validate = (input, value = input.value) => {
        const raw = normalize(value), number = Number(raw);
        let error = '';
        if (raw && (!/^-?(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw) || !Number.isFinite(number))) error = 'عدد معتبر وارد کنید.';
        else if (raw) {
            const min = input.getAttribute('min'), max = input.getAttribute('max'), step = input.getAttribute('step') ?? '1';
            if (min !== null && number < Number(min)) error = `حداقل مقدار ${min} است.`;
            else if (max !== null && number > Number(max)) error = `حداکثر مقدار ${max} است.`;
            else if (step !== 'any' && Number(step) > 0) {
                const quotient = (number - Number(min ?? 0)) / Number(step);
                if (Math.abs(quotient - Math.round(quotient)) > 1e-7) error = `گام عدد باید ${step} باشد.`;
            }
        }
        input.setCustomValidity(error);
        return !error;
    };
    const display = input => {
        const before = input.value, position = input.selectionStart;
        const count = position === null ? null : normalize(before.slice(0, position)).length;
        input.value = format(before);
        if (count !== null && document.activeElement === input) {
            let cursor = 0, seen = 0;
            while (cursor < input.value.length && seen < count) { if (input.value[cursor] !== ',') seen++; cursor++; }
            input.setSelectionRange(cursor, cursor);
        }
        validate(input);
    };
    const selector = 'input[data-number-input]:not([data-number-managed])';
    const mount = input => {
        if (input.hasAttribute('data-number-managed')) return;
        input.dataset.numberInput = '1';
        input.type = 'text'; input.inputMode = input.step === '1' || !input.step ? 'numeric' : 'decimal'; input.dir = 'ltr';
        display(input);
    };
    const enhance = root => {
        if (root.matches?.('input[type="number"]')) mount(root);
        root.querySelectorAll?.('input[type="number"]').forEach(mount);
    };
    // Handlers receive the raw value, then the visible field is formatted again.
    // This preserves existing delegated listeners and draft/calculation code.
    const rawDuringEvent = input => {
        const start = input.selectionStart;
        const caret = start === null ? null : normalize(input.value.slice(0, start)).length;
        input.value = normalize(input.value);
        if (caret !== null) input.setSelectionRange(caret, caret);
        validate(input);
        queueMicrotask(() => { if (input.isConnected) display(input); });
    };
    window.BuildinoNumeric = Object.freeze({ normalize, format, validate, display, mount, enhance });
    for (const name of ['input', 'change', 'blur']) document.addEventListener(name, event => {
        if (event.target.matches?.(selector) && !event.isComposing) rawDuringEvent(event.target);
    }, true);
    for (const name of ['click', 'submit']) document.addEventListener(name, event => {
        const form = event.target.closest?.('form, .swal2-popup');
        form?.querySelectorAll(selector).forEach(rawDuringEvent);
    }, true);
    document.addEventListener('formdata', event => {
        const names = new Set([...event.target.querySelectorAll('input[data-number-input], input[data-number-managed]')].filter(input => input.name && !input.disabled).map(input => input.name));
        for (const name of names) {
            const values = event.formData.getAll(name);
            event.formData.delete(name);
            for (const value of values) event.formData.append(name, typeof value === 'string' ? normalize(value) : value);
        }
    }, true);
    document.addEventListener('reset', event => queueMicrotask(() => event.target.querySelectorAll(selector).forEach(display)), true);
    const start = () => {
        enhance(document);
        new MutationObserver(records => {
            for (const record of records) {
                if (record.type === 'childList') record.addedNodes.forEach(node => { if (node.nodeType === 1) enhance(node); });
                else if (record.target.matches(selector) || record.target.matches('input[type="number"]')) {
                    if (record.target.type === 'number') mount(record.target); else display(record.target);
                }
            }
        }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['value', 'min', 'max', 'step', 'type'] });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
})();
