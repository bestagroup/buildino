<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import { ApiError, apiRequest, rowsFromPayload } from '../../../lib/management-api';
import { display, payloadFor, primaryFields, readValue, structure, type StructureKey, type StructureUi } from '../../../lib/building-structure';
import { useStructureScope } from '../../../lib/structure-scope';
import type { CrudField, CrudRecord, ResourcePageProps } from '../../../types/management';

const props = defineProps<ResourcePageProps & { structureUi: StructureUi }>();
const meta = computed(() => structure[props.resourceKey as StructureKey]);
const root = ref<HTMLElement | null>(null), editor = ref(false), mode = ref<'create' | 'edit'>('create');
const rows = ref<CrudRecord[]>([]), loading = ref(false), pageError = ref(''), search = ref('');
const page = ref(1), lastPage = ref(1), total = ref(0), contextId = ref('');
const form = ref<CrudRecord>({}), selected = ref<CrudRecord | null>(null), step = ref(0);
const formErrors = ref<Record<string, string[]>>({}), formError = ref(''), saving = ref(false), lookupsLoading = ref(false);
const deleteTarget = ref<CrudRecord | null>(null), deleting = ref(false), discard = ref(false), snapshot = ref('');
const notice = ref(''), choosingScope = ref(false);
let listTicket = 0, initializing = false, disposed = false;
const scope = useStructureScope(props.resourceKey as StructureKey, props.structureUi, contextId);
const { buildingId, blockId, buildings, blocks, floors, context } = scope;
const child = computed(() => props.siblingResources.find(row => row.key === meta.value.child));
const parent = computed(() => props.siblingResources.find(row => row.key === meta.value.parent));
const ready = computed(() => !meta.value.context || Boolean(context.value));
const canCreate = computed(() => Boolean(props.resource.create && props.structureUi.canCreate && ready.value && (!meta.value.context || context.value?.create) && (props.resourceKey !== 'buildings' || props.structureUi.createParents.length)));
const allowed = (row: CrudRecord, action: 'update' | 'delete') => Boolean(props.resource[action] && (meta.value.context ? context.value?.[action] : (props.structureUi.abilities[String(row.id)] ?? props.structureUi.parentAbilities[String(row.complex_id)] ?? props.structureUi.globalAbilities)?.[action]));
const canViewChild = (row: CrudRecord) => Boolean(child.value && (meta.value.context ? context.value?.childView : (props.structureUi.abilities[String(row.id)] ?? props.structureUi.parentAbilities[String(row.complex_id)] ?? props.structureUi.globalAbilities)?.childView));
const labels = computed(() => ['complexes', 'buildings'].includes(props.resourceKey) ? ['مشخصات اصلی', 'نشانی و تنظیمات', 'بررسی نهایی'] : ['اطلاعات ' + meta.value.singular, 'بررسی نهایی']);
const reviewStep = computed(() => labels.value.length - 1);
const visibleRows = computed(() => rows.value.filter(row => JSON.stringify(row).toLocaleLowerCase('fa').includes(search.value.trim().toLocaleLowerCase('fa'))));
const rowTitle = (row: CrudRecord) => {
    const title = String(row.title || '');
    const number = props.resourceKey === 'floors' ? 'طبقه ' + row.floor_number : props.resourceKey === 'units' ? 'واحد ' + row.unit_number : '';
    return number ? number + (title && title !== number ? ' · ' + title : '') : title || String(row.code || 'بدون عنوان');
};
const operationUrl = (template: string, row: CrudRecord | null = null) => template.replace(/\{([^}]+)}/g, (_, name: string) => {
    const value = name === 'id' ? row?.id : name === meta.value.context ? contextId.value : row?.[name];
    if (value === null || value === undefined || value === '') throw new Error('ابتدا محدوده عملیات را انتخاب کنید.');
    return encodeURIComponent(String(value));
});
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus({ preventScroll: true }); };
const updateUrl = () => {
    const url = new URL(window.location.href);
    if (meta.value.context) {
        for (const key of ['complex_id', 'building_id', 'block_id', 'floor_id']) url.searchParams.delete(key);
        scope.query().forEach((value, key) => url.searchParams.set(key, value));
    }
    url.searchParams.delete('create'); window.history.replaceState(window.history.state, '', url);
};
const loadRows = async (requestedPage = 1) => {
    const ticket = ++listTicket;
    if (!ready.value || !props.resource.list) { rows.value = []; loading.value = false; return; }
    loading.value = true; pageError.value = ''; rows.value = [];
    try {
        const url = new URL(operationUrl(props.resource.list.url), window.location.origin);
        if (props.resourceKey === 'buildings') {
            const complex = new URL(window.location.href).searchParams.get('complex_id');
            if (complex && /^\d+$/.test(complex)) url.searchParams.set('complex_id', complex);
        }
        url.searchParams.set('page', String(requestedPage));
        const result = await apiRequest<{ data: CrudRecord[]; meta?: { current_page?: number; last_page?: number; total?: number } }>(url.toString(), { csrfToken: props.csrfToken });
        if (ticket !== listTicket || disposed) return;
        rows.value = rowsFromPayload<CrudRecord>(result); page.value = result.meta?.current_page ?? 1; lastPage.value = result.meta?.last_page ?? 1; total.value = result.meta?.total ?? rows.value.length;
    } catch (error) { if (ticket === listTicket && !disposed) pageError.value = error instanceof Error ? error.message : 'اطلاعات دریافت نشد.'; }
    finally { if (ticket === listTicket && !disposed) loading.value = false; }
};
const changeContext = (level: 'building' | 'block' | 'floor') => {
    if (level === 'building') scope.selectBuilding();
    if (level === 'block') scope.selectBlock();
    listTicket++; rows.value = []; loading.value = false; pageError.value = ''; search.value = ''; notice.value = '';
};
const confirmContext = () => { if (ready.value) { choosingScope.value = false; updateUrl(); void loadRows(); void focus('#structure-list-title'); } };
const relatedUrl = (row: CrudRecord) => {
    const url = new URL(child.value?.url || window.location.href, window.location.origin);
    url.search = '';
    if (meta.value.context) scope.query().forEach((value, key) => url.searchParams.set(key, value));
    else if (props.resourceKey === 'buildings' && row.complex_id) url.searchParams.set('complex_id', String(row.complex_id));
    url.searchParams.set(meta.value.childContext, String(row.id));
    return url.pathname + url.search;
};
const parentUrl = computed(() => {
    const url = new URL(parent.value?.url || '/management/operations/buildings', window.location.origin);
    if (props.resourceKey === 'floors' && buildingId.value) url.searchParams.set('building_id', buildingId.value);
    if (props.resourceKey === 'units') {
        if (buildingId.value) url.searchParams.set('building_id', buildingId.value);
        if (blockId.value) url.searchParams.set('block_id', blockId.value);
    }
    return url.pathname + url.search;
});
const fields = computed<CrudField[]>(() => (props.resource.fields ?? []).map(field => {
    let result = { ...field };
    if (field.type === 'text' && ['unit_number', 'code', 'title'].includes(field.name)) result.maxlength = field.name === 'title' ? 255 : 100;
    if (field.name === 'floor_number') result.help = 'همکف: ۰؛ طبقات زیرزمین: عدد منفی؛ طبقات بالاتر: عدد مثبت.';
    if (field.name === 'complex_id') {
        result = { ...result, lookup: undefined, options: [...props.structureUi.createParents] };
        if (mode.value === 'edit' && form.value.complex_id && !result.options?.some(option => String(option.value) === String(form.value.complex_id))) result.options?.push({ value: String(form.value.complex_id), label: display(readValue(selected.value || {}, 'complex.title')) });
    }
    if (field.name === 'province' && props.structureUi.locations.length) result = { ...result, type: 'select', options: props.structureUi.locations.map(row => ({ value: row.name, label: row.name })) };
    if (field.name === 'city' && props.structureUi.locations.length) result = { ...result, type: 'select', options: (props.structureUi.locations.find(row => row.name === form.value.province)?.cities ?? []).map(row => ({ value: row.name, label: row.label })) };
    if (['province', 'city'].includes(field.name) && result.options && form.value[field.name] && !result.options.some(row => row.value === form.value[field.name])) result.options.push({ value: String(form.value[field.name]), label: String(form.value[field.name]) });
    if (field.type === 'number') {
        if (field.name !== 'floor_number' && !['latitude', 'longitude'].includes(field.name)) result.min = 0;
        if (field.name === 'latitude') { result.min = -90; result.max = 90; }
        if (field.name === 'longitude') { result.min = -180; result.max = 180; }
        if (field.name === 'bedrooms') result.max = 50;
    }
    return result;
}));
const groupFields = (index: number) => labels.value.length === 2 ? fields.value : fields.value.filter(field => primaryFields.has(field.name) === (index === 0));
const activeFields = computed(() => groupFields(step.value));
const review = computed(() => fields.value.filter(field => form.value[field.name] !== '' && form.value[field.name] !== null && form.value[field.name] !== undefined).map(field => ({ label: field.label, value: field.options?.find(option => String(option.value) === String(form.value[field.name]))?.label ?? display(form.value[field.name]) })));
watch(() => form.value.province, (value, old) => { if (editor.value && !initializing && value !== old) form.value.city = ''; });
const openEditor = async (requestedMode: 'create' | 'edit', row: CrudRecord | null = null) => {
    if (saving.value || (requestedMode === 'create' ? !canCreate.value : !row || !allowed(row, 'update'))) return;
    initializing = true; mode.value = requestedMode; selected.value = row; step.value = 0; formError.value = ''; formErrors.value = {}; discard.value = false; notice.value = '';
    form.value = Object.fromEntries((props.resource.fields ?? []).map(field => [field.name, row ? readValue(row, field.name) ?? field.default ?? (field.type === 'checkbox' ? false : '') : field.default ?? (field.type === 'checkbox' ? false : '')]));
    const complex = new URL(window.location.href).searchParams.get('complex_id');
    if (requestedMode === 'create' && props.resourceKey === 'buildings') form.value.complex_id = props.structureUi.createParents.find(row => String(row.value) === complex)?.value ?? (props.structureUi.createParents.length === 1 ? props.structureUi.createParents[0].value : '');
    snapshot.value = JSON.stringify(form.value); editor.value = true;
    await focus('#structure-editor-title'); initializing = false;
};
const closeEditor = (force = false) => {
    if (saving.value) return;
    if (!force && JSON.stringify(form.value) !== snapshot.value) { discard.value = true; return; }
    editor.value = false; discard.value = false; void focus('#structure-list-title');
};
const validate = (index: number) => {
    const errors: Record<string, string[]> = {};
    for (const field of groupFields(index)) {
        const value = form.value[field.name];
        if ((field.required || (mode.value === 'create' && field.required_on_create)) && (typeof value === 'string' ? !value.trim() : value === null || value === undefined)) errors[field.name] = ['این فیلد را تکمیل کنید.'];
        else if (field.type === 'number' && value !== '' && value !== null && value !== undefined) {
            const number = Number(value);
            if (!Number.isFinite(number) || (field.min !== undefined && number < field.min) || (field.max !== undefined && number > field.max) || ((!field.step || String(field.step) === '1') && !Number.isInteger(number))) errors[field.name] = ['مقدار عددی معتبر وارد کنید.'];
        }
    }
    formErrors.value = errors; return Object.keys(errors).length === 0;
};
const submit = async () => {
    if (saving.value || lookupsLoading.value) return;
    formError.value = '';
    if (step.value < reviewStep.value) {
        if (validate(step.value)) { step.value++; await focus('#structure-step-title'); } else await focus('[aria-invalid="true"]');
        return;
    }
    for (let index = 0; index < reviewStep.value; index++) if (!validate(index)) { step.value = index; await focus('[aria-invalid="true"]'); return; }
    const operation = mode.value === 'edit' ? props.resource.update : props.resource.create;
    if (!operation || (mode.value === 'edit' ? !selected.value || !allowed(selected.value, 'update') : !canCreate.value)) return;
    saving.value = true;
    try {
        await apiRequest(operationUrl(operation.url, selected.value), { method: operation.method ?? (mode.value === 'edit' ? 'PATCH' : 'POST'), csrfToken: props.csrfToken, body: JSON.stringify(payloadFor(fields.value, form.value, mode.value)) });
        saving.value = false; closeEditor(true); notice.value = 'اطلاعات ' + meta.value.singular + ' ذخیره شد.'; await loadRows(page.value);
    } catch (error) {
        formError.value = error instanceof Error ? error.message : 'اطلاعات ذخیره نشد.';
        if (error instanceof ApiError) formErrors.value = error.errors;
        const invalid = Object.keys(formErrors.value)[0]; step.value = invalid && labels.value.length === 3 && !primaryFields.has(invalid) ? 1 : 0;
        await focus(Object.keys(formErrors.value).length ? '[aria-invalid="true"]' : '#structure-step-title');
    } finally { saving.value = false; }
};
const remove = async () => {
    if (deleting.value || !deleteTarget.value || !allowed(deleteTarget.value, 'delete') || !props.resource.delete) return;
    deleting.value = true;
    try {
        await apiRequest(operationUrl(props.resource.delete.url, deleteTarget.value), { method: 'DELETE', csrfToken: props.csrfToken });
        deleteTarget.value = null; notice.value = meta.value.singular + ' حذف شد.'; await loadRows(page.value);
        if (!rows.value.length && page.value > 1) await loadRows(page.value - 1);
    } catch (error) { pageError.value = error instanceof Error ? error.message : 'حذف انجام نشد.'; deleteTarget.value = null; }
    finally { deleting.value = false; }
};
const beforeLeave = (event: BeforeUnloadEvent) => { if (editor.value && (saving.value || JSON.stringify(form.value) !== snapshot.value)) { event.preventDefault(); event.returnValue = ''; } };
onMounted(async () => {
    const url = new URL(window.location.href);
    scope.initialize(url.searchParams);
    choosingScope.value = !ready.value;
    if (ready.value && meta.value.context) updateUrl();
    await loadRows();
    if (disposed) return;
    if (url.searchParams.get('create') === '1' && canCreate.value) { updateUrl(); await openEditor('create'); }
    window.addEventListener('beforeunload', beforeLeave);
});
onBeforeUnmount(() => { disposed = true; listTicket++; window.removeEventListener('beforeunload', beforeLeave); });
</script>

<template>
    <Head :title="resource.title" />
    <div ref="root" class="ownership-page structure-page" data-structure-page>
        <nav class="structure-breadcrumb" aria-label="مسیر صفحه"><a href="/management">داشبورد</a><span>/</span><a v-if="parent" :href="parentUrl">{{ parent.title }}</a><span v-if="parent">/</span><strong>{{ resource.title }}</strong></nav>
        <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 21V5l8-2v18M12 8h8v13M7 8h2M7 12h2M7 16h2M15 11h2M15 15h2M2 21h20"/></svg></div><div><h2>{{ resource.title }}</h2><p>{{ resource.description }}</p></div></header>
        <template v-if="!editor">
            <section v-if="meta.context" class="ownership-card structure-context" aria-labelledby="structure-context-title">
                <template v-if="choosingScope">
                    <div class="ownership-card__heading"><div><h3 id="structure-context-title">انتخاب {{ resource.context?.[0]?.label }}</h3><p>از ساختمان شروع کنید و محل {{ meta.singular }} را مشخص کنید.</p></div></div>
                    <div class="structure-context-grid">
                        <label class="ownership-field"><span>ساختمان</span><select v-model="buildingId" data-structure-building data-select2="off" @change="changeContext('building')"><option value="">انتخاب ساختمان</option><option v-for="item in buildings" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select></label>
                        <label v-if="resourceKey !== 'blocks'" class="ownership-field"><span>بلوک</span><select v-model="blockId" data-structure-block data-select2="off" :disabled="!buildingId || !blocks.length" @change="changeContext('block')"><option value="">{{ !buildingId ? 'ابتدا ساختمان را انتخاب کنید' : blocks.length ? 'انتخاب بلوک' : 'بلوکی ثبت نشده است' }}</option><option v-for="item in blocks" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select></label>
                        <label v-if="resourceKey === 'units'" class="ownership-field"><span>طبقه</span><select v-model="contextId" data-structure-floor data-select2="off" :disabled="!blockId || !floors.length" @change="changeContext('floor')"><option value="">{{ !blockId ? 'ابتدا بلوک را انتخاب کنید' : floors.length ? 'انتخاب طبقه' : 'طبقه‌ای ثبت نشده است' }}</option><option v-for="item in floors" :key="item.id" :value="String(item.id)">{{ item.title || item.label }}</option></select></label>
                    </div>
                    <p v-if="!buildings.length" class="ownership-empty">ساختمان قابل دسترسی برای این بخش وجود ندارد.</p>
                    <p v-else-if="buildingId && !ready && ((resourceKey !== 'blocks' && !blocks.length) || (resourceKey === 'units' && blockId && !floors.length))" class="ownership-empty">{{ resourceKey === 'units' && blocks.length ? 'طبقه‌ای' : 'بلوکی' }} ثبت نشده است. {{ parent ? 'ابتدا آن را در بخش مربوط ثبت کنید.' : 'برای تکمیل ساختار با مدیر ساختمان هماهنگ کنید.' }}</p>
                    <div class="ownership-actions structure-context-actions"><a v-if="parent" class="ownership-button" :href="parentUrl">مدیریت {{ parent.title }}</a><button type="button" class="ownership-button ownership-button--primary" data-structure-context-confirm :disabled="!ready" @click="confirmContext">نمایش {{ resource.title }}</button></div>
                </template>
                <div v-else class="structure-context-summary"><div><span id="structure-context-title">{{ resource.context?.[0]?.label }} انتخاب‌شده</span><strong>{{ context?.label }}</strong></div><button type="button" class="ownership-button" data-structure-change-context @click="choosingScope = true; focus('[data-structure-building]')">تغییر محدوده</button></div>
            </section>
            <section v-if="!choosingScope" class="ownership-card" aria-labelledby="structure-list-title">
                <div class="ownership-card__heading ownership-card__heading--split structure-toolbar"><div><h3 id="structure-list-title" tabindex="-1">فهرست {{ resource.title }}</h3><p>{{ context?.label || (resourceKey === 'buildings' ? 'اطلاعات ساختمان و دسترسی به بلوک‌ها، طبقات و واحدها' : 'ساختار و اطلاعات ثبت‌شده') }}</p></div><div class="ownership-actions"><a v-if="resourceKey === 'buildings' && structureUi.canSetup" class="ownership-button" :href="structureUi.setupUrl">راه‌اندازی ساختمان</a><button v-if="canCreate" type="button" class="ownership-button ownership-button--primary" data-structure-add @click="openEditor('create')">+ افزودن {{ meta.singular }}</button></div></div>
                <div v-if="notice" class="structure-success" role="status">{{ notice }}</div>
                <div v-if="ready" class="structure-tools"><label class="ownership-field"><span class="structure-sr-only">جست‌وجو در همین صفحه</span><input v-model="search" type="search" placeholder="جست‌وجو در همین صفحه"></label><button type="button" class="ownership-button" :disabled="loading" @click="loadRows(page)">به‌روزرسانی</button></div>
                <div v-if="loading" class="ownership-empty" role="status" aria-live="polite">در حال دریافت اطلاعات…</div>
                <div v-else-if="pageError" class="structure-error" role="alert"><p>{{ pageError }}</p><button class="ownership-button" type="button" @click="loadRows(page)">تلاش مجدد</button></div>
                <div v-else-if="!ready" class="ownership-empty">ابتدا {{ resource.context?.[0]?.label }} را انتخاب کنید.</div>
                <div v-else-if="!visibleRows.length" class="ownership-empty"><strong>{{ search ? 'نتیجه‌ای پیدا نشد.' : 'هنوز ' + meta.singular + ' ثبت نشده است.' }}</strong><p>{{ search ? 'عبارت جست‌وجو را تغییر دهید.' : canCreate ? 'با دکمه افزودن، اطلاعات را ثبت کنید.' : 'اطلاعات ثبت‌شده در این بخش نمایش داده می‌شود.' }}</p></div>
                <div v-else class="structure-records">
                    <article v-for="row in visibleRows" :key="String(row.id)" class="structure-record" :aria-label="rowTitle(row)">
                        <div class="structure-record__heading"><div><span v-if="row.code" class="structure-code" dir="ltr">{{ row.code }}</span><h4>{{ rowTitle(row) }}</h4></div><span v-if="row.is_active !== undefined" class="structure-status" :class="{ 'is-inactive': !row.is_active }">{{ display(row.is_active) }}</span></div>
                        <dl class="structure-record__info"><template v-for="column in (resource.columns ?? []).filter(column => !['id', 'title', 'code', 'is_active', 'sort_order', 'currency'].includes(column.key))" :key="column.key"><dt>{{ column.label }}</dt><dd>{{ column.key === 'usage_type' ? ({ residential: 'مسکونی', commercial: 'تجاری', office: 'اداری', other: 'سایر' } as Record<string, string>)[String(readValue(row, column.key))] || display(readValue(row, column.key)) : display(readValue(row, column.key)) }}</dd></template></dl>
                        <div class="structure-record__actions"><a v-if="canViewChild(row) && child" :href="relatedUrl(row)" class="ownership-button ownership-button--primary" data-structure-child>مدیریت {{ child.title }}</a><button v-if="allowed(row, 'update')" type="button" class="ownership-button" data-structure-edit @click="openEditor('edit', row)">ویرایش اطلاعات</button><button v-if="allowed(row, 'delete')" type="button" class="ownership-button structure-delete" @click="deleteTarget = row">حذف</button></div>
                    </article>
                </div>
                <nav v-if="ready && lastPage > 1" class="structure-pagination" aria-label="صفحه‌بندی"><button class="ownership-button" type="button" :disabled="loading || page <= 1" @click="loadRows(page - 1)">صفحه قبل</button><span>صفحه {{ page }} از {{ lastPage }} · {{ total }} مورد</span><button class="ownership-button" type="button" :disabled="loading || page >= lastPage" @click="loadRows(page + 1)">صفحه بعد</button></nav>
            </section>
        </template>
        <section v-else class="ownership-card structure-editor" aria-labelledby="structure-editor-title" :aria-busy="saving">
            <div class="ownership-card__heading ownership-card__heading--split structure-toolbar"><div><h3 id="structure-editor-title" tabindex="-1">{{ mode === 'edit' ? 'ویرایش' : 'افزودن' }} {{ meta.singular }}</h3><p>{{ context?.label || 'اطلاعات موردنیاز را تکمیل کنید.' }}</p></div><button type="button" class="ownership-button" :disabled="saving" @click="closeEditor()">بازگشت به فهرست</button></div>
            <nav class="ownership-flow structure-steps" :style="{ '--structure-steps': labels.length }" aria-label="مراحل ثبت اطلاعات"><span v-for="(label, index) in labels" :key="label" :class="{ 'is-current': index === step, 'is-complete': index < step }" :aria-current="index === step ? 'step' : undefined"><b>{{ index + 1 }}</b>{{ label }}</span></nav>
            <h4 id="structure-step-title" tabindex="-1">{{ labels[step] }}</h4>
            <form id="structure-form" novalidate @submit.prevent="submit">
                <fieldset :disabled="saving"><DynamicForm v-if="step < reviewStep" v-model="form" :fields="activeFields" :mode="mode" :lookup-base="lookupBase" :csrf-token="csrfToken" :errors="formErrors" @loading="lookupsLoading = $event" /><dl v-else class="structure-review"><template v-for="item in review" :key="item.label"><dt>{{ item.label }}</dt><dd>{{ item.value }}</dd></template></dl></fieldset>
                <div v-if="formError" class="structure-error" role="alert">{{ formError }}</div>
                <div v-if="discard" class="structure-discard" role="alert"><p>تغییرات ذخیره نشده‌اند. از فرم خارج می‌شوید؟</p><button type="button" class="ownership-button" @click="discard = false">ادامه ویرایش</button><button type="button" class="ownership-button structure-delete" @click="closeEditor(true)">خروج بدون ذخیره</button></div>
                <div class="ownership-actions structure-form-actions"><button v-if="step > 0" type="button" class="ownership-button" :disabled="saving" @click="step--; formError = ''; focus('#structure-step-title')">مرحله قبل</button><button type="submit" class="ownership-button ownership-button--primary" :disabled="saving || lookupsLoading" data-structure-next>{{ saving ? 'در حال ذخیره…' : step === reviewStep ? 'تأیید و ذخیره' : 'ادامه' }}</button></div>
            </form>
        </section>
        <UiConfirmDialog :open="Boolean(deleteTarget)" :title="'حذف ' + meta.singular" :message="'«' + (deleteTarget ? rowTitle(deleteTarget) : '') + '» حذف شود؟ حذف موارد دارای زیرمجموعه ممکن است مجاز نباشد.'" confirm-label="حذف" :busy="deleting" @cancel="!deleting && (deleteTarget = null)" @confirm="remove" />
    </div>
</template>
