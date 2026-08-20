import {
    isValidJalaaliDate,
    toGregorian,
    toJalaali,
} from 'jalaali-js';
import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css';
import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js';
import '../css/buildino-jalali-datepicker.css';

const dateTypes = new Set([
    'date',
    'datetime-local',
    'time',
]);
const candidateSelector = [
    'input[type="date"]',
    'input[type="datetime-local"]',
    'input[type="time"]',
    'input[data-buildino-date-type]',
].join(', ');
const disabledSelector = [
    '[data-jdp="off"]',
    '[data-native-date]',
].join(', ');

let fieldSequence = 0;

const normalizeDigits = (value) => String(value ?? '')
    .trim()
    .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)));

const pad = (value) => String(value).padStart(2, '0');

const normalizeTime = (value) => {
    const match = normalizeDigits(value).match(
        /^(\d{1,2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/
    );

    if (! match) {
        return null;
    }

    const hour = Number(match[1]);
    const minute = Number(match[2]);
    const second = Number(match[3] ?? 0);

    if (hour > 23 || minute > 59 || second > 59) {
        return null;
    }

    return `${pad(hour)}:${pad(minute)}`;
};

const parseServerDate = (value) => {
    const match = normalizeDigits(value).match(
        /^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?)?/
    );

    if (! match) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
        time: match[4] === undefined
            ? ''
            : `${match[4]}:${match[5]}`,
    };
};

const normalizeServerValue = (value, type) => {
    if (! value) {
        return '';
    }

    if (type === 'time') {
        return normalizeTime(value) ?? '';
    }

    const parsed = parseServerDate(value);

    if (! parsed) {
        return '';
    }

    const date = `${parsed.year}-${pad(parsed.month)}-${pad(parsed.day)}`;

    if (type === 'date') {
        return date;
    }

    return parsed.time
        ? `${date}T${parsed.time}`
        : date;
};

const serverToJalali = (value, type) => {
    if (! value) {
        return '';
    }

    if (type === 'time') {
        return normalizeTime(value) ?? '';
    }

    const parsed = parseServerDate(value);

    if (! parsed) {
        return '';
    }

    try {
        const jalali = toJalaali(
            parsed.year,
            parsed.month,
            parsed.day
        );
        const date = `${jalali.jy}/${pad(jalali.jm)}/${pad(jalali.jd)}`;

        if (type === 'date') {
            return date;
        }

        return parsed.time
            ? `${date} ${parsed.time}`
            : date;
    } catch {
        return '';
    }
};

const jalaliToServer = (value, type) => {
    const normalized = normalizeDigits(value);

    if (! normalized) {
        return '';
    }

    if (type === 'time') {
        return normalizeTime(normalized);
    }

    const match = normalized.match(
        /^(\d{3,4})[/-](\d{1,2})[/-](\d{1,2})(?:\s+(\d{1,2}):(\d{2})(?::\d{2})?)?$/
    );

    if (! match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);

    if (! isValidJalaaliDate(year, month, day)) {
        return null;
    }

    let time = '';

    if (type === 'datetime-local') {
        if (match[4] === undefined) {
            return null;
        }

        time = normalizeTime(`${match[4]}:${match[5]}`);

        if (! time) {
            return null;
        }
    }

    try {
        const gregorian = toGregorian(year, month, day);
        const date = `${gregorian.gy}-${pad(gregorian.gm)}-${pad(gregorian.gd)}`;

        return type === 'date'
            ? date
            : `${date}T${time}`;
    } catch {
        return null;
    }
};

const dateInputs = (root = document) => {
    const inputs = [];

    if (
        root instanceof HTMLInputElement
        && root.matches(candidateSelector)
    ) {
        inputs.push(root);
    }

    root.querySelectorAll?.(candidateSelector).forEach((input) => {
        inputs.push(input);
    });

    return [...new Set(inputs)];
};

const dateType = (input) => {
    const configured = input.dataset.buildinoDateType;

    if (dateTypes.has(configured)) {
        return configured;
    }

    const nativeType = input.getAttribute('type') || 'text';

    return dateTypes.has(nativeType)
        ? nativeType
        : null;
};

const applyLimit = (input, attribute, dataAttribute, type) => {
    const value = input.getAttribute(attribute);

    if (! value) {
        return;
    }

    const converted = type === 'time'
        ? normalizeTime(value)
        : serverToJalali(value, type).split(' ')[0];

    if (converted) {
        input.setAttribute(dataAttribute, converted);
    }

    input.removeAttribute(attribute);
};

const enhanceInput = (input) => {
    if (
        input.dataset.buildinoDatepicker === '1'
        || input.matches(disabledSelector)
    ) {
        return;
    }

    const type = dateType(input);

    if (! type) {
        return;
    }

    const originalName = input.getAttribute('name') || '';
    const originalValue = input.value;
    const serverValue = normalizeServerValue(originalValue, type);
    let valueInput = null;

    if (originalName) {
        valueInput = document.createElement('input');
        valueInput.type = 'hidden';
        valueInput.name = originalName;
        valueInput.id = `buildino-date-value-${++fieldSequence}`;
        valueInput.value = serverValue;
        valueInput.defaultValue = serverValue;
        valueInput.disabled = input.disabled;
        valueInput.dataset.buildinoGregorianValue = type;

        if (input.hasAttribute('form')) {
            valueInput.setAttribute('form', input.getAttribute('form'));
        }

        input.removeAttribute('name');
        input.insertAdjacentElement('afterend', valueInput);
    }

    input.type = 'text';
    input.dataset.buildinoDateType = type;
    input.dataset.buildinoDatepicker = '1';
    input.setAttribute('data-jdp', '');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('dir', 'ltr');
    input.classList.add('buildino-jalali-input');

    if (type === 'date') {
        input.setAttribute('data-jdp-only-date', '');
        input.placeholder ||= '۱۴۰۵/۰۵/۳۰';
    } else if (type === 'time') {
        input.setAttribute('data-jdp-only-time', '');
        input.setAttribute('data-jdp-has-second', 'false');
        input.placeholder ||= '۱۲:۳۰';
    } else {
        input.setAttribute('data-jdp-has-second', 'false');
        input.placeholder ||= '۱۴۰۵/۰۵/۳۰ ۱۲:۳۰';
    }

    if (type === 'time') {
        applyLimit(input, 'min', 'data-jdp-min-time', type);
        applyLimit(input, 'max', 'data-jdp-max-time', type);
    } else {
        applyLimit(input, 'min', 'data-jdp-min-date', type);
        applyLimit(input, 'max', 'data-jdp-max-date', type);
    }

    const jalaliValue = serverToJalali(serverValue, type);
    input.value = jalaliValue;
    input.defaultValue = jalaliValue;

    const synchronize = () => {
        const converted = jalaliToServer(input.value, type);

        input.setCustomValidity(
            converted === null
                ? type === 'time'
                    ? 'زمان معتبر را به صورت ساعت و دقیقه انتخاب کنید.'
                    : 'تاریخ شمسی معتبر را از تقویم انتخاب کنید.'
                : ''
        );

        if (valueInput) {
            valueInput.value = converted ?? '';
            valueInput.disabled = input.disabled;
        }
    };

    input.addEventListener('input', synchronize);
    input.addEventListener('change', synchronize);
    input.addEventListener('jdp:change', synchronize);
    synchronize();
};

const enhance = (root = document) => {
    dateInputs(root).forEach(enhanceInput);
};

const observer = new MutationObserver((records) => {
    records.forEach((record) => {
        record.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                enhance(node);
            }
        });
    });
});

const boot = () => {
    enhance(document);

    window.jalaliDatepicker?.startWatch({
        autoReadOnlyInput: false,
        hasSecond: 'attr',
        hideAfterChange: true,
        hideAfterChangeWithTime: false,
        maxDate: 'attr',
        maxTime: 'attr',
        minDate: 'attr',
        minTime: 'attr',
        mode: 'attr',
        persianDigits: true,
        position: 'right',
        selector: 'input[data-buildino-datepicker="1"]',
        showCloseBtn: true,
        showEmptyBtn: true,
        showTodayBtn: true,
        zIndex: 11050,
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}

document.addEventListener('shown.bs.modal', (event) => {
    enhance(event.target);
});

document.addEventListener('buildino:dates-changed', (event) => {
    enhance(event.detail?.root || document);
});

window.BuildinoJalaliDatepicker = {
    enhance,
    jalaliToServer,
    serverToJalali,
};

window.dispatchEvent(new CustomEvent('buildino:datepicker-ready'));
