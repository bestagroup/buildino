(() => {
    'use strict';
    if (!document.body.classList.contains('portal-provider')) return;
    const localize = node => {
        const update = text => {
            if (!text.parentElement || text.parentElement.closest('script,style,input,textarea,code,svg')) return;
            const value = text.nodeValue.replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[Number(digit)]).replace(/\bIRR\b/g,'ریال').replace(/\bIRT\b/g,'تومان').replace(/\baccepted\b/g,'پذیرفته‌شده');
            if (value !== text.nodeValue) text.nodeValue = value;
        };
        if (node.nodeType === Node.TEXT_NODE) update(node);
        else if (node.nodeType === Node.ELEMENT_NODE) { const walker = document.createTreeWalker(node,NodeFilter.SHOW_TEXT); while (walker.nextNode()) update(walker.currentNode); }
    };
    localize(document.body);
    new MutationObserver(records => { for (const record of records) { if (record.type === 'characterData') localize(record.target); else record.addedNodes.forEach(localize); } }).observe(document.body,{subtree:true,childList:true,characterData:true});
    const clearErrors = form => {
        form.querySelectorAll('.provider-form-error,.provider-field-error').forEach(node => node.remove());
        form.querySelectorAll('[data-provider-invalid]').forEach(field => { field.removeAttribute('aria-invalid'); field.removeAttribute('data-provider-invalid'); const ids = (field.getAttribute('aria-describedby') || '').split(' ').filter(id => !id.startsWith('provider-error-')); if (ids.length) field.setAttribute('aria-describedby', ids.join(' ')); else field.removeAttribute('aria-describedby'); window.BuildinoSelect2?.refresh(field); });
    };
    document.querySelectorAll('form[data-portal-form^="provider-"]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.portalBusy === '1' || form.dataset.residentUncertain === '1') { event.preventDefault(); event.stopImmediatePropagation(); return; }
            clearErrors(form);
            if (!form.reportValidity()) { event.preventDefault(); event.stopImmediatePropagation(); }
        }, true);
        form.closest('.modal').addEventListener('hide.bs.modal', event => { if (form.dataset.portalBusy === '1' && form.dataset.providerSaved !== '1') event.preventDefault(); });
    });
    document.addEventListener('buildino:portal-form-error', event => {
        const {form,error} = event.detail;
        if (!form.matches('[data-portal-form^="provider-"]')) return;
        clearErrors(form);
        const summary = document.createElement('div'); summary.className = 'provider-form-error'; summary.setAttribute('role','alert'); summary.tabIndex = -1; summary.textContent = error.message;
        form.querySelector('.modal-body').prepend(summary);
        let first;
        for (const [name,messages] of Object.entries(error.payload?.errors || {})) {
            const field = form.elements.namedItem(name);
            if (!(field instanceof HTMLElement)) continue;
            const note = document.createElement('small'); note.className = 'provider-field-error'; note.id = 'provider-error-' + form.dataset.portalForm + '-' + name; note.textContent = [].concat(messages).join('؛ ');
            field.setAttribute('aria-invalid','true'); field.dataset.providerInvalid = '1'; field.setAttribute('aria-describedby', ((field.getAttribute('aria-describedby') || '') + ' ' + note.id).trim()); field.closest('.portal-field')?.append(note); window.BuildinoSelect2?.refresh(field); first ||= field;
        }
        if (error.uncertain) { form.dataset.residentUncertain = '1'; const link = document.createElement('a'); link.href = location.pathname + location.search; link.className = 'portal-action-button'; link.textContent = 'بررسی اطلاعات ثبت‌شده'; summary.append(document.createElement('br'),link); }
        const target = first?.matches('.select2-hidden-accessible') ? first.nextElementSibling?.querySelector('.select2-selection') : first;
        (target || summary).focus();
    });
})();
