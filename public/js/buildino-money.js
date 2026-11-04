(() => {
    'use strict';

    const normalize = value => String(value ?? '')
        .replace(/[۰-۹]/g, digit => String(digit.charCodeAt(0) - 1776))
        .replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 1632))
        .replace(/[,٬\s]/g, '');

    const format = value => {
        const digits = normalize(value);
        return /^\d*$/.test(digits) ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',') : digits;
    };

    const mount = input => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.dir = 'ltr';
        input.pattern = '[0-9,۰-۹٠-٩٬\\s]+';
        input.value = format(input.value);
        input.addEventListener('input', () => {
            const before = input.value;
            const position = input.selectionStart ?? before.length;
            const digitsBefore = normalize(before.slice(0, position)).length;
            input.value = format(before);
            let cursor = 0;
            let seen = 0;
            while (cursor < input.value.length && seen < digitsBefore) {
                if (input.value[cursor] !== ',') seen++;
                cursor++;
            }
            input.setSelectionRange(cursor, cursor);
        });
    };

    window.BuildinoMoney = { normalize, format, mount };
})();
