(() => {
    'use strict';
    const root = document.querySelector('[data-staff-page]');
    if (!root) return;
    const normalize = value => String(value).replace(/[۰-۹٠-٩]/g, digit => String(digit.charCodeAt(0) - (digit >= '۰' ? 1776 : 1632))).replace(/ي/g,'ی').replace(/ك/g,'ک').toLocaleLowerCase('fa').trim();
    const form = root.querySelector('[data-staff-form]');
    if (form) {
        const type = form.querySelector('#staff-type'), save = form.querySelector('[data-staff-save]');
        const error = form.querySelector('[data-staff-services-error]');
        let dirty = false, busy = false;
        const validateServices = showError => {
            const active = form.querySelector('[data-staff-services]:not([hidden])');
            const inputs = [...active.querySelectorAll('input')], valid = inputs.some(input => input.checked);
            inputs[0]?.setCustomValidity(valid ? '' : 'حداقل یک خدمت را انتخاب کنید.');
            if (showError || valid) error.hidden = valid;
            inputs.forEach(input => { if (!valid && !error.hidden) { input.setAttribute('aria-invalid','true'); input.setAttribute('aria-describedby',error.id); } else { input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); } });
            return valid;
        };
        const switchType = () => {
            form.querySelectorAll('[data-staff-services]').forEach(group => {
                const active = group.dataset.staffServices === type.value;
                group.hidden = !active;
                group.querySelectorAll('input').forEach(input => { input.disabled = !active; input.setCustomValidity(''); input.removeAttribute('aria-invalid'); input.removeAttribute('aria-describedby'); });
            });
            error.hidden = true;
            validateServices(false);
        };
        type.addEventListener('change', switchType);
        form.addEventListener('input', event => {
            dirty = true;
            if (event.target.name === 'mobile') event.target.value = normalize(event.target.value).replace(/[\s-]/g,'');
        });
        form.addEventListener('change', () => {
            dirty = true; validateServices(false);
            form.querySelector('[data-staff-permission-count]').textContent = form.querySelectorAll('[name="permissions[]"]:checked').length.toLocaleString('fa-IR') + ' انتخاب';
        });
        form.addEventListener('invalid', event => { if (event.target.name === 'services[]') validateServices(true); }, true);
        form.addEventListener('submit', event => {
            if (busy) { event.preventDefault(); return; }
            if (!validateServices(true) || !form.reportValidity()) { event.preventDefault(); return; }
            busy = true; save.disabled = true; save.textContent = 'در حال ذخیره…'; form.setAttribute('aria-busy','true');
        });
        root.querySelectorAll('[data-staff-cancel]').forEach(link => link.addEventListener('click', async event => {
            if (!dirty && !busy) return;
            event.preventDefault();
            if (busy) return;
            const confirmed = window.Swal ? (await window.Swal.fire({title:'خروج از فرم',text:'تغییرات ذخیره نشده‌اند. از فرم خارج می‌شوید؟',showCancelButton:true,confirmButtonText:'خروج بدون ذخیره',cancelButtonText:'ادامه ویرایش'})).isConfirmed : window.confirm('تغییرات ذخیره نشده‌اند. از فرم خارج می‌شوید؟');
            if (confirmed) { dirty = false; location.assign(link.href); }
        }));
        window.addEventListener('beforeunload', event => { if (dirty && !busy) { event.preventDefault(); event.returnValue = ''; } });
        window.addEventListener('pageshow', event => { if (event.persisted) { busy = false; save.disabled = false; save.textContent = form.querySelector('[name=_method]') ? 'ذخیره تغییرات' : 'ثبت کارمند'; form.removeAttribute('aria-busy'); } });
        validateServices(false);
        root.querySelector('[data-staff-error-summary]')?.focus();
    }
    const filters = root.querySelector('[data-staff-filters]');
    if (filters) {
        const search = filters.querySelector('[data-staff-search]'), type = filters.querySelector('[data-staff-filter-type]'), status = filters.querySelector('[data-staff-filter-status]');
        const rows = [...root.querySelectorAll('[data-staff-record]')];
        const update = () => {
            let count = 0;
            rows.forEach(row => { row.hidden = !normalize(row.dataset.search).includes(normalize(search.value)) || Boolean(type.value && row.dataset.type !== type.value) || Boolean(status.value && row.dataset.status !== status.value); if (!row.hidden) count++; });
            root.querySelector('[data-staff-result]').textContent = count.toLocaleString('fa-IR') + ' کارمند از ' + rows.length.toLocaleString('fa-IR') + ' کارمند این صفحه';
            root.querySelector('[data-staff-filter-empty]').hidden = count > 0;
        };
        search.addEventListener('input',update); type.addEventListener('change',update); status.addEventListener('change',update);
        filters.hidden = false; update();
    }
})();
