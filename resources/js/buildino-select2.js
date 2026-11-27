import $ from 'jquery';
import select2Factory from 'select2';
import 'select2/dist/css/select2.css';
import '../css/buildino-select2.css';

window.$ = $;
window.jQuery = $;

if (! $.fn.select2 && typeof select2Factory === 'function') {
    select2Factory(window, $);
}

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
    if (data.element?.hidden || data.element?.closest('optgroup[hidden]')) return null;
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
    // Keep calendar menus inside its click boundary and stacking context.
    const calendar = select.closest('jdp-container');
    if (calendar) return $(calendar);
    const dialog = select.closest('dialog');
    if (dialog) {
        let host = dialog.querySelector(':scope > [data-select2-dialog-host]');
        if (!host) {
            host = document.createElement('div');
            host.dataset.select2DialogHost = '1';
            host.style.cssText = 'position:relative;height:0;overflow:visible';
            dialog.append(host);
        }
        return $(host);
    }
    const parent = select.closest(
        '.modal, .crud-drawer, .crud-modal, .swal2-popup, [role="dialog"], [role="alertdialog"]'
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
        // Retired opt-out attributes may still exist in cached or dynamic forms.
        // jQuery otherwise reads data-select2="off" as the plugin instance.
        if (select.getAttribute('data-select2') === 'off') {
            select.removeAttribute('data-select2');
            if (!element.hasClass('select2-hidden-accessible')) element.removeData('select2');
        }
        select.removeAttribute('data-native-select');

        if (element.hasClass('select2-hidden-accessible')) {
            synchronize(select);
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
                    fields[fields.length - 1]?.setAttribute('aria-label', 'جست‌وجوی گزینه‌ها');
                    fields[fields.length - 1]?.focus();
                });
            });

        select.dataset.buildinoSelect2 = '1';
        // Native change is the shared contract for Vue v-model and Blade forms.
        // Namespaced refreshes and real native events must never be bridged twice.
        element.off('.coreflow').off('.buildinoChange').on('change.buildinoChange', event => {
            if (event.namespace) return;
            if (!event.originalEvent && !select.closest('.dt-container')) select.dispatchEvent(new Event('change', { bubbles: true }));
            synchronize(select);
        });
        synchronize(select);
    });
};

const refresh = (select) => {
    if (! (select instanceof HTMLSelectElement)) {
        return;
    }

    if (!select.isConnected) {
        destroy(select);
        return;
    }

    if (! $(select).hasClass('select2-hidden-accessible')) {
        enhance(select);
        return;
    }

    synchronize(select);
    $(select).trigger('change.select2');
};

const synchronize = select => {
    const instance = $(select).data('select2');
    if (!instance) return;
    const disabled = select.matches(':disabled');
    if (instance.options.get('disabled') !== disabled) {
        instance.options.set('disabled', disabled);
        instance.trigger(disabled ? 'disable' : 'enable', {});
        if (disabled) instance.close();
    }
    // Vue may replace option text without replacing the option element.
    for (const option of select.options) {
        const data = instance.dataAdapter.item($(option));
        data.text = option.textContent; data.disabled = option.disabled;
    }
    const label = select.getAttribute('aria-label') || [...(select.labels || [])].map(label => {
        const copy = label.cloneNode(true);
        copy.querySelectorAll('select, .select2, small, button').forEach(node => node.remove());
        return copy.textContent.trim();
    }).join(' ');
    if (label) instance.$selection.attr('aria-label', label);
    for (const name of ['aria-describedby', 'aria-invalid', 'aria-required', 'aria-busy']) {
        const value = name === 'aria-required' ? String(select.required || select.dataset.required === '1') : select.getAttribute(name);
        if (value === null) instance.$selection.removeAttr(name); else instance.$selection.attr(name, value);
    }
};

const destroy = (root = document) => {
    selectElements(root).forEach((select) => {
        const element = $(select);

        if (! element.hasClass('select2-hidden-accessible')) {
            return;
        }

        element.off('.buildino').off('.coreflow').off('.buildinoChange').select2('destroy');
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

        if (record.type === 'attributes' && record.target instanceof HTMLFieldSetElement) {
            selectElements(record.target).forEach(queueSelect);
        }

        record.removedNodes.forEach(node => {
            if (node instanceof Element && !node.isConnected) destroy(node);
        });

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
        characterData: true,
        attributes: true,
        attributeFilter: ['disabled', 'required', 'multiple', 'aria-invalid', 'aria-describedby', 'aria-busy'],
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

// Labels can activate the original select even while it is visually clipped.
// Keep Vue-owned markup intact and route that activation to its existing widget.
const activateEnhancedSelect = (event) => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement)) return;
    const instance = $(select).data('select2');
    if (!instance || !select.classList.contains('select2-hidden-accessible')) return;

    event.preventDefault();
    // Do not let Select2's outside-click listener immediately close the widget.
    event.stopPropagation();
    if (select.matches(':disabled')) return;
    if (!instance.isOpen()) instance.open();
};

for (const type of ['pointerdown', 'mousedown', 'click']) {
    document.addEventListener(type, activateEnhancedSelect, true);
}

document.addEventListener('reset', event => window.setTimeout(() => selectElements(event.target).forEach(refresh), 0), true);

document.addEventListener('invalid', event => {
    const select = event.target;
    if (!(select instanceof HTMLSelectElement) || !$(select).hasClass('select2-hidden-accessible')) return;
    event.preventDefault();
    const instance = $(select).data('select2');
    instance?.$selection.attr('aria-invalid', 'true');
    if (select.form && select.form.querySelector(':invalid') !== select) return;
    instance?.$selection.trigger('focus');
    if (!select.matches(':disabled')) $(select).select2('open');
}, true);

window.BuildinoSelect2 = {
    destroy,
    enhance,
    refresh,
};

window.dispatchEvent(new CustomEvent('buildino:select2-ready'));
