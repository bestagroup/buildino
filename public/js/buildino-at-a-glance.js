(() => {
    'use strict';
    const root = document.querySelector('[data-glance-page]');
    if (!root) return;
    const filters = root.querySelector('[data-glance-filters]');
    if (!filters) return;
    const search = root.querySelector('[data-glance-search]'), block = root.querySelector('[data-glance-block]'), status = root.querySelector('[data-glance-status]');
    const floors = [...root.querySelectorAll('[data-glance-floor]')], units = [...root.querySelectorAll('[data-glance-unit]')];
    const normalize = value => String(value || '').trim().toLocaleLowerCase('fa').replace(/[۰-۹]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit))).replace(/[٠-٩]/g, digit => String('٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ');
    const searchable = new Map(units.map(unit => [unit, normalize(unit.dataset.search)]));
    const number = value => value.toLocaleString('fa-IR');
    const update = () => {
        const term = normalize(search.value), state = status.value;
        let count = 0;
        units.forEach(unit => {
            const data = unit.dataset;
            const match = (!term || searchable.get(unit).includes(term)) && (!block.value || data.block === block.value) && (
                !state || state === 'shortfall' && Number(data.shortfall) > 0 || state === 'debt' && Number(data.debt) > 0 || state === 'surplus' && Number(data.surplus) > 0 || state === 'vacant' && data.occupied === '0' || state === 'inactive' && data.active === '0'
            );
            unit.hidden = !match;
            if (match) count++;
        });
        floors.forEach(floor => {
            const visible = [...floor.querySelectorAll('[data-glance-unit]')].filter(unit => !unit.hidden).length;
            floor.hidden = visible === 0;
            floor.querySelector('[data-glance-floor-count]').textContent = number(visible);
        });
        root.querySelector('[data-glance-result]').textContent = number(count) + ' واحد از ' + number(units.length) + ' واحد نمایش داده می‌شود';
        root.querySelector('[data-glance-empty]').hidden = count !== 0;
    };
    search.addEventListener('input', update);
    block.addEventListener('change', update);
    status.addEventListener('change', update);
    root.querySelector('[data-glance-reset]').addEventListener('click', () => { search.value = ''; block.value = ''; status.value = ''; update(); search.focus({ preventScroll: true }); });
    filters.hidden = false;
    update();
})();
