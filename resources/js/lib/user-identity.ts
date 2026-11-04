import type { CrudField, CrudRecord } from '../types/management';

const limits: Record<string, number> = { first_name: 255, last_name: 255, mobile: 20, email: 255, national_code: 20 };

export function userIdentityFields(fields: CrudField[]): CrudField[] {
    const order = Object.keys(limits);
    return fields.filter(field => Object.hasOwn(limits, field.name))
        .map(field => ({ ...field, maxlength: limits[field.name],
            ...(field.name === 'mobile' ? { type: 'tel', placeholder: '09… یا +98…' } : {}) }))
        .sort((a, b) => order.indexOf(a.name) - order.indexOf(b.name));
}

export function validateUserIdentity(form: CrudRecord): Record<string, string[]> {
    const errors: Record<string, string[]> = {};
    for (const name of ['first_name', 'last_name', 'mobile']) if (!String(form[name] || '').trim()) errors[name] = ['این فیلد را تکمیل کنید.'];
    for (const [name, max] of Object.entries(limits)) {
        if (String(form[name] || '').length > max) errors[name] = ['طول این مقدار بیش از حد مجاز است.'];
    }
    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(form.email))) errors.email = ['ایمیل معتبر وارد کنید.'];
    return errors;
}

export function userIdentityValues(form: CrudRecord): CrudRecord {
    return Object.fromEntries(Object.keys(limits).map(name => [name, String(form[name] || '').trim() || null]));
}
