<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FinanceFrame from '../../../components/management/FinanceFrame.vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import { ApiError, apiRequest, rowsFromPayload } from '../../../lib/management-api';
import { readValue, payloadFor } from '../../../lib/building-structure';
import { day, invoiceLabels, money, type InvoiceWorkspace } from '../../../lib/invoice-workspace';
import type { CrudField, CrudRecord, CrudResource } from '../../../types/management';

const props = defineProps<{ resourceKey: string; title: string; resource: CrudResource; workspace: InvoiceWorkspace; csrfToken: string }>();
const root = ref<HTMLElement | null>(null), buildingId = ref(''), search = ref(''), rows = ref<CrudRecord[]>([]);
const loading = ref(false), busy = ref(false), lookupLoading = ref(false), error = ref(''), success = ref(''), uncertain = ref(false);
const view = ref<'list' | 'detail' | 'editor'>('list'), selected = ref<CrudRecord | null>(null), editing = ref(false), step = ref(0);
const form = ref<CrudRecord>({}), errors = ref<Record<string, string[]>>({}), discard = ref(false), deleting = ref(false);
const page = ref(1), lastPage = ref(1), total = ref(0), operation = ref<NonNullable<CrudResource['actions']>[number] | null>(null);
let ticket = 0, disposed = false, snapshot = '';
const building = computed(() => props.workspace.buildings.find(row => String(row.id) === buildingId.value));
const allowed = (action: string, row: CrudRecord | null = null) => Boolean(building.value?.abilities[props.workspace.prefix + '.' + action]
    || props.resourceKey === 'expenses' && props.workspace.blocks.some(block => String(block.building_id) === buildingId.value && block.abilities[action] && (!row || String(row.block_id) === String(block.value))));
const currency = computed(() => ({ IRR: 'ریال', IRT: 'تومان' }[building.value?.currency || ''] || building.value?.currency || 'واحد پول ساختمان'));
const canCreate = computed(() => Boolean(props.resource.create && allowed('create')));
const filtered = computed(() => rows.value.filter(row => (row.building_id === undefined || String(row.building_id) === buildingId.value) && JSON.stringify(row).toLocaleLowerCase('fa').includes(search.value.trim().toLocaleLowerCase('fa'))));
const rowTitle = (row: CrudRecord) => String(row.title || row.account_holder_name || (props.resourceKey === 'loyalty-rules' ? 'قاعده نسخه ' + row.version : props.resourceKey === 'loyalty-claims' ? row.user_name || 'درخواست جایزه' : props.title + ' · ' + row.id));
const labels: Record<string, string> = { ...invoiceLabels, approved: 'تأییدشده', rejected: 'ردشده', posted: 'ثبت‌شده', payment_verified: 'تأیید پرداخت', equal: 'مساوی بین واحدها', area: 'بر اساس متراژ', persons: 'بر اساس تعداد ساکنین', owner: 'مالک', resident: 'ساکن / مستأجر', unit: 'کیف پول واحد', electricity: 'برق', gas: 'گاز', water: 'آب', phone: 'تلفن', internet: 'اینترنت', municipality: 'شهرداری', other: 'سایر' };
const fields = computed<CrudField[]>(() => {
    const base = (operation.value ? operation.value.fields || [] : props.resource.fields || []).filter(field => field.name !== 'building_id' && field.type !== 'json').map(field => {
        const result = { ...field };
        if (result.type === 'number') { result.min = result.name === 'amount' || result.name === 'points' || result.name === 'required_points' ? 1 : 0; result.step = 1; if (result.name === 'amount') result.label += ' (' + currency.value + ')'; }
        if (result.type === 'text') result.maxlength = ['reference_number', 'invoice_number', 'bill_identifier', 'payment_identifier', 'provider'].includes(result.name) ? 100 : result.name === 'bank_name' ? 120 : result.name === 'iban' ? 34 : result.name === 'account_number' ? 50 : result.name === 'card_number' ? 19 : 255;
        if (result.type === 'textarea') result.maxlength = result.name === 'reason' ? 2000 : 5000;
        if (props.resourceKey === 'loyalty-rules' && result.name === 'points') result.max = 1000000;
        if (['fund_id', 'financial_category_id', 'building_bank_account_id'].includes(result.name)) result.depends_on = 'building_id';
        const lookup = props.workspace.lookups[result.name];
        if (lookup) {
            result.lookup = undefined; result.depends_on = undefined;
            result.options = lookup.filter(row => (!row.building_id || String(row.building_id) === buildingId.value) && (result.name !== 'block_id' || building.value?.abilities['expenses.create'] || row.abilities?.create || editing.value && String(row.value) === String(selected.value?.block_id)));
            if (result.name === 'block_id' && !building.value?.abilities['expenses.create'] && (!editing.value || selected.value?.block_id)) { result.required = true; result.help = 'بلوک مجاز برای ثبت هزینه را انتخاب کنید.'; }
            if (result.name === 'building_bank_account_id' && !result.options.length) result.help = 'حساب بانکی فعال و تأییدشده‌ای وجود ندارد. ابتدا در بخش حساب‌های بانکی، حساب را ثبت و تأیید کنید.';
        }
        if (result.name === 'bank_reference') result.required = true;
        if (props.resourceKey === 'incomes' && result.name === 'status') result.default = 'posted';
        return result;
    });
    if (props.resourceKey === 'loyalty-rules' && !operation.value) base.push(...[
        { name: 'amount_step', label: 'مبلغ هر گام امتیاز (' + currency.value + ')', min: 1 },
        { name: 'minimum_amount', label: 'حداقل مبلغ پرداخت (' + currency.value + ')', min: 0 },
        { name: 'maximum_points', label: 'سقف امتیاز هر پرداخت', min: 1 },
        { name: 'expires_days', label: 'اعتبار امتیاز (روز)', min: 1, max: 3650 },
    ].map(field => ({ ...field, type: 'number', step: 1, help: 'اختیاری؛ در صورت خالی‌بودن، مقدار پیش‌فرض سامانه اعمال می‌شود.' })));
    return base;
});
const mainNames = new Set(['title', 'amount', 'expense_date', 'income_date', 'allocation_method', 'payer_responsibility']);
const split = computed(() => !operation.value && ['expenses', 'incomes', 'loyalty-rules'].includes(props.resourceKey));
const steps = computed(() => operation.value ? fields.value.length ? ['اطلاعات عملیات', 'بررسی و اجرا'] : ['بررسی و اجرا'] : split.value ? ['اطلاعات اصلی', 'جزئیات و تنظیمات', 'بررسی نهایی'] : ['اطلاعات', 'بررسی نهایی']);
const activeFields = computed(() => !split.value ? fields.value : fields.value.filter(field => props.resourceKey === 'loyalty-rules' ? ['event_type', 'points', 'is_active'].includes(field.name) === (step.value === 0) : mainNames.has(field.name) === (step.value === 0)));
const review = computed(() => fields.value.filter(field => form.value[field.name] !== '' && form.value[field.name] !== undefined && form.value[field.name] !== null).map(field => ({ label: field.label, value: field.options?.find(option => String(option.value) === String(form.value[field.name]))?.label ?? format(form.value[field.name], field.name) })));
const format = (value: unknown, key: string): string => {
    if (value === null || value === undefined || value === '') return '—';
    if (key === 'building_id') return building.value?.title || '—';
    const option = props.workspace.lookups[key]?.find(row => String(row.value) === String(value));
    if (option) return option.label;
    if (typeof value === 'boolean') return key === 'is_verified' ? value ? 'تأییدشده' : 'تأییدنشده' : key === 'is_default' ? value ? 'پیش‌فرض' : 'عادی' : value ? 'فعال' : 'غیرفعال';
    if (/(?:_date|_at)$/.test(key)) return day(String(value));
    if (typeof value === 'number' || /amount|points/.test(key)) return money(value);
    if (typeof value === 'object') return String((value as CrudRecord).title || '—');
    return labels[String(value)] || String(value);
};
const visibleActions = (row: CrudRecord) => (props.resource.actions || []).filter(action => {
    if (!building.value?.abilities[props.workspace.actionPermissions[action.key]]) return false;
    if (props.resourceKey === 'bank-accounts') return !row.is_verified;
    if (props.resourceKey === 'loyalty-claims' || props.resourceKey === 'bill-payments') return row.status === 'pending';
    if (props.resourceKey === 'wallet-payouts') return action.key === 'paid' ? row.status === 'approved' : action.key === 'reject' ? ['pending', 'approved'].includes(String(row.status)) : row.status === 'pending';
    return true;
});
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus(); };
const endpoint = (path: string, row = selected.value) => path.replace(/\{([^}]+)}/g, (_, key: string) => {
    const value = key === 'building_id' ? buildingId.value : row?.[key]; if (value === undefined || value === null || value === '') throw new Error('محدوده عملیات کامل نیست.'); return encodeURIComponent(String(value));
});
const report = (caught: unknown) => {
    errors.value = caught instanceof ApiError ? caught.errors : {};
    const messages = Object.values(errors.value).flat();
    error.value = messages.join('؛ ') || (caught instanceof Error ? caught.message : 'اطلاعات دریافت نشد. دوباره تلاش کنید.');
    void focus('[data-finance-error]');
};
const load = async (target = 1) => {
    const version = ++ticket; rows.value = []; error.value = ''; if (!building.value || !props.resource.list) { loading.value = false; total.value = 0; lastPage.value = 1; return; }
    loading.value = true;
    try {
        const url = new URL(endpoint(props.resource.list.url), window.location.origin); url.searchParams.set('page', String(target)); url.searchParams.set('per_page', '20'); url.searchParams.set('building_id', buildingId.value);
        const payload = await apiRequest<{ data: CrudRecord[]; meta?: { current_page: number; last_page: number; total: number }; current_page?: number; last_page?: number; total?: number }>(url.toString(), { csrfToken: props.csrfToken });
        if (disposed || version !== ticket) return;
        rows.value = rowsFromPayload(payload); page.value = payload.meta?.current_page || payload.current_page || 1; lastPage.value = payload.meta?.last_page || payload.last_page || 1; total.value = payload.meta?.total ?? payload.total ?? rows.value.length;
    } catch (caught) { if (!disposed && version === ticket) report(caught); }
    finally { if (version === ticket) loading.value = false; }
};
const changeBuilding = () => { selected.value = null; search.value = ''; success.value = ''; const url = new URL(window.location.href); buildingId.value ? url.searchParams.set('building_id', buildingId.value) : url.searchParams.delete('building_id'); window.history.replaceState(window.history.state, '', url); void load(); };
const openEditor = async (row: CrudRecord | null = null, action: typeof operation.value = null) => {
    if (busy.value || !building.value) return;
    if (action ? !row || !visibleActions(row).some(item => item.key === action.key) : row ? !allowed('update', row) : !canCreate.value) return;
    selected.value = row; editing.value = Boolean(row && !action); operation.value = action; step.value = 0; error.value = ''; success.value = ''; errors.value = {}; uncertain.value = false;
    form.value = { building_id: Number(buildingId.value) };
    for (const field of fields.value) form.value[field.name] = action ? field.default ?? (field.type === 'checkbox' ? false : '') : row ? readValue(row, field.name) ?? (row.configuration as CrudRecord | undefined)?.[field.name] ?? field.default ?? (field.type === 'checkbox' ? false : '') : field.default ?? (field.type === 'checkbox' ? false : '');
    if (form.value.status === '' && props.resourceKey === 'incomes') form.value.status = 'posted';
    view.value = 'editor'; snapshot = JSON.stringify(form.value); discard.value = false;
    await focus('#finance-record-editor-title');
};
const closeEditor = (force = false) => {
    if (busy.value) return;
    if (!force && JSON.stringify(form.value) !== snapshot) { discard.value = true; return; }
    view.value = selected.value ? 'detail' : 'list'; operation.value = null; discard.value = false; void focus(selected.value ? '#finance-record-detail-title' : '#finance-record-list-title');
};
const validate = (checkFields = fields.value) => {
    const next: Record<string, string[]> = {};
    for (const field of checkFields) {
        const value = form.value[field.name], empty = value === null || value === undefined || typeof value === 'string' && !value.trim();
        if (field.required && empty) next[field.name] = ['این فیلد را تکمیل کنید.'];
        else if (!empty && field.type === 'number' && (!Number.isSafeInteger(Number(value)) || field.min !== undefined && Number(value) < field.min || field.max !== undefined && Number(value) > field.max)) next[field.name] = ['مقدار عددی معتبر وارد کنید.'];
        else if (!empty && field.maxlength && String(value).length > field.maxlength) next[field.name] = ['متن از حداکثر طول مجاز بیشتر است.'];
        else if (!empty && ['date', 'datetime-local'].includes(field.type || '') && !/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2})?$/.test(String(value))) next[field.name] = ['تاریخ یا ساعت معتبر انتخاب کنید.'];
    }
    if (form.value.starts_at && form.value.ends_at && String(form.value.ends_at) <= String(form.value.starts_at)) next.ends_at = ['پایان باید بعد از شروع باشد.'];
    if (props.resourceKey === 'bank-accounts' && !operation.value && (String(form.value.iban || '').trim().length < 24 || String(form.value.iban).trim().length > 34)) next.iban = ['شماره شبا باید بین ۲۴ تا ۳۴ نویسه باشد.'];
    errors.value = next;
    if (Object.keys(next).length) {
        const key = Object.keys(next)[0]; step.value = split.value && !(props.resourceKey === 'loyalty-rules' ? ['event_type', 'points', 'is_active'].includes(key) : mainNames.has(key)) ? 1 : 0;
        error.value = Object.values(next).flat().join('؛ '); void focus('[data-finance-error]'); return false;
    }
    return true;
};
const submit = async () => {
    if (busy.value || lookupLoading.value || uncertain.value) return;
    if (step.value < steps.value.length - 1 && split.value && step.value === 0) {
        if (!validate(activeFields.value)) return;
        step.value++; await focus('#finance-record-step-title'); return;
    }
    if (!validate()) return;
    if (step.value < steps.value.length - 1) { step.value++; await focus('#finance-record-step-title'); return; }
    const action = operation.value || (editing.value ? props.resource.update : props.resource.create); if (!action) return;
    busy.value = true; error.value = '';
    try {
        const body = payloadFor(fields.value, form.value, editing.value ? 'edit' : 'create');
        if (props.resourceKey === 'expenses' && editing.value && String(body.block_id ?? '') === String(selected.value?.block_id ?? '')) delete body.block_id;
        if (!operation.value && ['expenses', 'incomes'].includes(props.resourceKey)) body.building_id = Number(buildingId.value);
        if (props.resourceKey === 'loyalty-rules' && !operation.value) { const configuration: CrudRecord = {}; for (const key of ['amount_step', 'minimum_amount', 'maximum_points', 'expires_days']) { if (body[key] !== null) configuration[key] = body[key]; delete body[key]; } body.configuration = Object.keys(configuration).length ? configuration : null; }
        await apiRequest(endpoint(action.url), { csrfToken: props.csrfToken, method: action.method || (editing.value ? 'PATCH' : 'POST'), body: JSON.stringify(body) });
        view.value = 'list'; selected.value = null; operation.value = null; snapshot = JSON.stringify(form.value); await load(page.value); success.value = 'عملیات با موفقیت ثبت شد.';
    } catch (caught) { report(caught); step.value = 0; if (!(caught instanceof ApiError) || caught.status >= 500) { uncertain.value = true; error.value += ' نتیجه ثبت مشخص نیست؛ قبل از تکرار، فهرست را بررسی کنید.'; } }
    finally { busy.value = false; }
};
const remove = async () => {
    if (busy.value || !selected.value || !props.resource.delete || !allowed('delete', selected.value)) return;
    busy.value = true;
    try { await apiRequest(endpoint(props.resource.delete.url), { method: 'DELETE', csrfToken: props.csrfToken }); deleting.value = false; view.value = 'list'; selected.value = null; await load(page.value); if (!rows.value.length && page.value > 1) await load(page.value - 1); success.value = 'سند حذف شد.'; }
    catch (caught) { report(caught); deleting.value = false; }
    finally { busy.value = false; }
};
const beforeLeave = (event: BeforeUnloadEvent) => { if (busy.value || view.value === 'editor' && JSON.stringify(form.value) !== snapshot) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => { const requested = props.workspace.initial.building_id; buildingId.value = props.workspace.buildings.some(row => String(row.id) === requested) ? requested! : !requested && props.workspace.buildings.length === 1 ? String(props.workspace.buildings[0].id) : ''; void load(); window.addEventListener('beforeunload', beforeLeave); });
onBeforeUnmount(() => { disposed = true; ticket++; window.removeEventListener('beforeunload', beforeLeave); });
</script>

<template>
    <FinanceFrame :title="title" :resource-key="resourceKey" :workspace="workspace" :building-id="buildingId"><div ref="root">
        <div v-if="error" role="alert" tabindex="-1" class="ownership-alert ownership-alert--error" data-finance-error>{{ error }}</div><div v-if="success" role="status" class="ownership-alert ownership-alert--success">{{ success }}</div>
        <template v-if="view === 'list'">
            <section class="ownership-card"><div class="ownership-card__heading"><div><h3>انتخاب ساختمان</h3><p>{{ resource.description }}</p></div></div><label class="ownership-field"><span>ساختمان</span><select v-model="buildingId" data-finance-building data-select2="off" @change="changeBuilding"><option value="">انتخاب ساختمان</option><option v-for="item in workspace.buildings" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select></label><p v-if="!workspace.buildings.length" class="finance-empty">ساختمان قابل دسترسی وجود ندارد.</p></section>
            <section v-if="building" class="ownership-card" :aria-busy="loading"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-record-list-title" tabindex="-1">{{ title }}</h3><p>{{ money(total) }} مورد ثبت‌شده · {{ building.title }}</p></div><div class="finance-actions"><button type="button" class="ownership-button" :disabled="loading" @click="load(page)">به‌روزرسانی</button><button v-if="canCreate" type="button" class="ownership-button ownership-button--primary" data-finance-create @click="openEditor()">+ {{ resourceKey === 'bank-accounts' ? 'حساب جدید' : resourceKey === 'loyalty-rules' ? 'نسخه جدید قاعده' : resourceKey === 'wallet-payouts' ? 'درخواست برداشت' : resourceKey === 'bill-payments' ? 'ثبت قبض' : 'ثبت جدید' }}</button></div></div>
                <label class="ownership-field"><span>جست‌وجو در همین صفحه</span><input v-model="search" type="search" placeholder="عنوان، شماره یا وضعیت"></label><p class="finance-hint">مبالغ به {{ currency }} نمایش داده می‌شوند.</p>
                <div v-if="loading" class="finance-empty" role="status">در حال دریافت اطلاعات…</div><div v-else-if="!filtered.length" class="finance-empty">{{ search ? 'موردی در این صفحه پیدا نشد.' : 'هنوز موردی برای این ساختمان ثبت نشده است.' }}</div>
                <div v-else class="finance-table-region" role="region" :aria-label="title" tabindex="0"><table class="finance-table"><caption class="finance-sr-only">{{ title }} · {{ building.title }}</caption><thead><tr><th v-for="column in resource.columns?.filter(column => !['id', 'building_id', 'created_at'].includes(column.key))" :key="column.key" scope="col">{{ column.label }}</th><th scope="col">عملیات</th></tr></thead><tbody><tr v-for="row in filtered" :key="String(row.id)"><td v-for="column in resource.columns?.filter(column => !['id', 'building_id', 'created_at'].includes(column.key))" :key="column.key"><span v-if="column.key === 'status'" class="finance-status" :data-status="String(row.status)">{{ format(row.status, 'status') }}</span><bdi v-else>{{ format(readValue(row, column.key), column.key) }}</bdi></td><td><button type="button" class="ownership-button" data-finance-open @click="selected = row; view = 'detail'; focus('#finance-record-detail-title')">جزئیات و عملیات</button></td></tr></tbody></table></div>
                <nav v-if="lastPage > 1" class="ownership-actions finance-pagination" aria-label="صفحه‌بندی"><button type="button" class="ownership-button" :disabled="loading || page <= 1" @click="load(page - 1)">صفحه قبل</button><span>صفحه {{ money(page) }} از {{ money(lastPage) }}</span><button type="button" class="ownership-button" :disabled="loading || page >= lastPage" @click="load(page + 1)">صفحه بعد</button></nav>
            </section>
        </template>
        <section v-else-if="view === 'detail' && selected" class="ownership-card"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-record-detail-title" tabindex="-1">{{ rowTitle(selected) }}</h3><p>{{ building?.title }}</p></div><button type="button" class="ownership-button" @click="view = 'list'; focus('#finance-record-list-title')">بازگشت به فهرست</button></div><dl class="finance-review"><template v-for="column in resource.columns" :key="column.key"><dt>{{ column.label }}</dt><dd>{{ format(readValue(selected, column.key), column.key) }}</dd></template><template v-if="selected.description"><dt>توضیحات</dt><dd>{{ selected.description }}</dd></template></dl><div class="ownership-actions"><button v-if="resource.update && allowed('update', selected)" type="button" class="ownership-button" data-finance-edit @click="openEditor(selected)">ویرایش اطلاعات</button><button v-for="action in visibleActions(selected)" :key="action.key" type="button" class="ownership-button" :class="{ 'ownership-button--primary': !['reject','fail'].includes(action.key), 'finance-danger': ['reject','fail'].includes(action.key) }" :data-finance-action="action.key" @click="openEditor(selected, action)">{{ action.title }}</button><button v-if="resource.delete && allowed('delete', selected)" type="button" class="ownership-button finance-danger" @click="deleting = true">حذف سند</button></div></section>
        <section v-else-if="view === 'editor'" class="ownership-card finance-editor" aria-labelledby="finance-record-editor-title" :aria-busy="busy"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-record-editor-title" tabindex="-1">{{ operation?.title || (editing ? 'ویرایش · ' : 'ثبت · ') + title }}</h3><p>{{ building?.title }}</p></div><button type="button" class="ownership-button" :disabled="busy" @click="closeEditor()">بازگشت</button></div>
            <nav class="ownership-flow" :style="{ '--finance-steps': steps.length }" aria-label="مراحل ثبت"><span v-for="(label, index) in steps" :key="label" :class="{ 'is-current': step === index, 'is-complete': step > index }" :aria-current="step === index ? 'step' : undefined"><b>{{ index + 1 }}</b>{{ label }}</span></nav><h4 id="finance-record-step-title" tabindex="-1">{{ steps[step] }}</h4>
            <form id="finance-record-form" novalidate @submit.prevent="submit"><fieldset :disabled="busy || uncertain"><DynamicForm v-if="step < steps.length - 1" v-model="form" :fields="activeFields" :mode="editing ? 'edit' : 'create'" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" :group-optional="false" @loading="lookupLoading = $event" /><template v-else><dl class="finance-review"><dt>ساختمان</dt><dd>{{ building?.title }}</dd><template v-for="item in review" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></template><template v-if="operation && !fields.length"><dt>سند</dt><dd>{{ selected ? rowTitle(selected) : '' }}</dd></template></dl><p v-if="operation?.confirm" class="finance-hint">{{ operation.confirm }}</p><p v-else-if="resourceKey === 'wallet-payouts' || resourceKey === 'bill-payments'" class="finance-hint">ثبت این درخواست، مبلغ را در کیف پول ساختمان مسدود می‌کند. پیش از تأیید، مبلغ و اطلاعات مقصد را بررسی کنید.</p><p v-else-if="resourceKey === 'loyalty-rules'" class="finance-hint">نسخه جدیدی از قاعده ساخته می‌شود؛ سوابق نسخه‌های قبلی حفظ می‌شوند.</p></template></fieldset>
                <div v-if="discard" class="finance-line" role="alert"><p>تغییرات ذخیره نشده‌اند. خارج می‌شوید؟</p><div class="finance-actions"><button type="button" class="ownership-button" @click="discard = false">ادامه ویرایش</button><button type="button" class="ownership-button finance-danger" @click="closeEditor(true)">خروج بدون ذخیره</button></div></div><div class="ownership-actions finance-pagination"><button v-if="step > 0" type="button" class="ownership-button" :disabled="busy" @click="step--; error = ''; focus('#finance-record-step-title')">مرحله قبل</button><button v-if="uncertain" type="button" class="ownership-button" @click="closeEditor(true); view = 'list'; load()">بررسی وضعیت در فهرست</button><button type="submit" class="ownership-button ownership-button--primary" :disabled="busy || lookupLoading || uncertain" data-finance-save>{{ busy ? 'در حال ثبت…' : step === steps.length - 1 ? 'تأیید و ثبت' : 'ادامه' }}</button></div>
            </form>
        </section>
        <UiConfirmDialog :open="deleting" title="حذف سند" message="این سند حذف شود؟ حذف ممکن است بر سوابق مالی اثر بگذارد." :busy="busy" confirm-label="تأیید حذف" @cancel="!busy && (deleting = false)" @confirm="remove" />
    </div></FinanceFrame>
</template>
