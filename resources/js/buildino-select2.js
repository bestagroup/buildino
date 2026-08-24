import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.css';
import '../css/buildino-select2.css';

window.$ = $;
window.jQuery = $;

if (! $.fn.select2 && typeof select2Factory === 'function') {
    select2Factory(window, $);
}

const disabledSelector = [
    '[data-select2="off"]',
    '[data-native-select]',
    'jdp-container select',
    '.swal2-container select',
    '.swal2-popup select',
    '[role="alertdialog"] select',
].join(', ');
const pendingSelects = new Set();
let flushScheduled = false;

const normalizeSearchText = (value) => String(value ?? '')
    .trim()
    .toLocaleLowerCase('fa')
    .normalize('NFKC')
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/[ۀة]/g, 'ه')
    .replace(/[ؤ]/g, 'و')
    .replace(/[إأٱ]/g, 'ا')
    .replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)))
    .replace(/[٠-٩]/g, (digit) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit)))
    .replace(/[\u064B-\u065F\u0670]/g, '');

const matcher = (params, data) => {
    const term = normalizeSearchText(params.term);

    if (! term) {
        return data;
    }

    if (Array.isArray(data.children)) {
        const children = data.children
            .map((child) => matcher(params, child))
            .filter(Boolean);

        return children.length
            ? { ...data, children }
            : null;
    }

    return normalizeSearchText(data.text).includes(term)
        ? data
        : null;
};

const language = {
    errorLoading: () => 'امکان دریافت نتایج وجود ندارد.',
    inputTooLong: () => 'عبارت جست‌وجو کوتاه‌تر شود.',
    inputTooShort: () => 'برای جست‌وجو عبارت بیشتری وارد کنید.',
    loadingMore: () => 'در حال دریافت نتایج بیشتر...',
    maximumSelected: () => 'حداکثر تعداد گزینه انتخاب شده است.',
    noResults: () => 'نتیجه‌ای پیدا نشد.',
    searching: () => 'در حال جست‌وجو...',
    removeAllItems: () => 'حذف همه گزینه‌ها',
};

const selectElements = (root = document) => {
    const elements = [];

    if (root instanceof HTMLSelectElement) {
        elements.push(root);
    }

    root.querySelectorAll?.('select').forEach((select) => {
        elements.push(select);
    });

    return [...new Set(elements)];
};

const dropdownParent = (select) => {
    const parent = select.closest(
        '.modal, .crud-drawer, .crud-modal, [role="dialog"]'
    );

    return parent ? $(parent) : $(document.body);
};

const enhance = (root = document) => {
    if (! $.fn.select2) {
        return;
    }

    selectElements(root).forEach((select) => {
        if (! select.isConnected) {
            return;
        }

        const element = $(select);

        if (select.matches(disabledSelector)) {
            if (element.hasClass('select2-hidden-accessible')) {
                destroy(select);
            }

            return;
        }

        if (element.hasClass('select2-hidden-accessible')) {
            element.trigger('change.select2');
            return;
        }

        const emptyOption = [...select.options]
            .find((option) => option.value === '');
        const required = select.required
            || select.dataset.required === '1';
        const placeholder = select.dataset.placeholder
            || emptyOption?.textContent?.trim()
            || 'انتخاب کنید';

        element.select2({
            allowClear: Boolean(emptyOption) && ! required,
            closeOnSelect: ! select.multiple,
            dir: select.dir || document.documentElement.dir || 'rtl',
            dropdownParent: dropdownParent(select),
            language,
            matcher,
            minimumResultsForSearch: 0,
            placeholder,
            width: select.dataset.select2Width || '100%',
        });

        element
            .off('select2:open.buildino')
            .on('select2:open.buildino', () => {
                window.setTimeout(() => {
                    const fields = document.querySelectorAll(
                        '.select2-container--open .select2-search__field'
                    );
                    fields[fields.length - 1]?.focus();
                });
            });

        select.dataset.buildinoSelect2 = '1';
    });
};

const refresh = (select) => {
    if (! (select instanceof HTMLSelectElement)) {
        return;
    }

    if (select.matches(disabledSelector)) {
        destroy(select);
        return;
    }

    if (! $(select).hasClass('select2-hidden-accessible')) {
        enhance(select);
        return;
    }

    $(select).trigger('change.select2');
};

const destroy = (root = document) => {
    selectElements(root).forEach((select) => {
        const element = $(select);

        if (! element.hasClass('select2-hidden-accessible')) {
            return;
        }

        element.off('.buildino').select2('destroy');
        delete select.dataset.buildinoSelect2;
    });
};

const queueSelect = (select) => {
    if (! (select instanceof HTMLSelectElement)) {
        return;
    }

    pendingSelects.add(select);

    if (flushScheduled) {
        return;
    }

    flushScheduled = true;
    window.requestAnimationFrame(() => {
        pendingSelects.forEach((pending) => refresh(pending));
        pendingSelects.clear();
        flushScheduled = false;
    });
};

const observer = new MutationObserver((records) => {
    records.forEach((record) => {
        const target = record.target instanceof Element
            ? record.target.closest('select')
            : record.target.parentElement?.closest('select');

        queueSelect(target);

        record.addedNodes.forEach((node) => {
            if (! (node instanceof Element)) {
                return;
            }

            if (node instanceof HTMLOptionElement) {
                queueSelect(node.closest('select'));
            }

            selectElements(node).forEach(queueSelect);
        });
    });
});

const boot = () => {
    enhance(document);
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

document.addEventListener('buildino:selects-changed', (event) => {
    enhance(event.detail?.root || document);
});

window.BuildinoSelect2 = {
    destroy,
    enhance,
    refresh,
};

window.dispatchEvent(new CustomEvent('buildino:select2-ready'));
