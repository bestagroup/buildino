<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import { ApiError, apiRequest } from '../../../lib/management-api';
import { day, money } from '../../../lib/invoice-workspace';
import { activePayment, closedRequest, normalizeRequest, personName, quoteStatuses, serviceActions, servicePriorities, serviceStatuses, type ServiceQuote, type ServiceRequestRow, type ServiceWorkspace } from '../../../lib/service-requests';
import type { CrudField, CrudRecord } from '../../../types/management';
import '../../../../css/finance-workspace.css';
import '../../../../css/service-requests.css';

const props = defineProps<{ workspace: ServiceWorkspace; csrfToken: string }>();
const root = ref<HTMLElement | null>(null), view = ref<'list' | 'detail' | 'editor'>('list');
const rows = ref<ServiceRequestRow[]>([]), selected = ref<ServiceRequestRow | null>(null), quote = ref<ServiceQuote | null>(null);
const loading = ref(false), busy = ref(false), lookupLoading = ref(false), uncertain = ref(false), error = ref(''), success = ref('');
const status = ref(''), buildingFilter = ref(''), search = ref(''), page = ref(1), lastPage = ref(1), total = ref(0);
const kind = ref('create'), step = ref(0), form = ref<CrudRecord>({}), errors = ref<Record<string, string[]>>({}), discard = ref(false), confirmation = ref('');
const providerSearch = ref(''), providers = ref<{ value: number; label: string }[]>([]), providerError = ref('');
let listTicket = 0, detailTicket = 0, providerTicket = 0, disposed = false, snapshot = '';
const request = <T,>(path: string, method = 'GET', body?: unknown) => apiRequest<T>(path, { csrfToken: props.csrfToken, method, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
const building = computed(() => props.workspace.buildings.find(row => row.id === Number(view.value === 'editor' && ['create', 'edit'].includes(kind.value) ? form.value.building_id : selected.value?.building_id)));
const currency = computed(() => ({ IRR: 'ریال', IRT: 'تومان' }[building.value?.currency || ''] || building.value?.currency || 'ریال'));
const buildingName = (id: number) => props.workspace.buildings.find(row => row.id === id)?.title || 'ساختمان ' + money(id);
const unitName = (id?: number | null) => props.workspace.units.find(row => row.id === id)?.label || (id ? 'واحد ' + money(id) : 'مشاعات ساختمان');
const availableUnits = computed(() => props.workspace.units.filter(row => row.building_id === Number(form.value.building_id) && (kind.value === 'edit' ? row.canUpdate : row.canCreate)));
const shown = computed(() => rows.value.filter(row => (!buildingFilter.value || String(row.building_id) === buildingFilter.value) && [row.request_number, row.title, personName(row.provider)].join(' ').includes(search.value.trim())));
const actions = computed(() => selected.value ? serviceActions(selected.value, props.workspace.buildings.find(row => row.id === selected.value?.building_id), props.workspace.userId) : []);
const primaryAction = computed(() => ['confirm', 'finish', 'start', 'quote', 'assign'].find(action => actions.value.includes(action)));
const labels: Record<string, string> = { create: 'درخواست جدید', edit: 'ویرایش درخواست', assign: 'تخصیص ارائه‌دهنده', quote: 'پیشنهاد قیمت', accept: 'پذیرش قیمت و تأمین مبلغ', start: 'شروع خدمت', finish: 'اعلام پایان خدمت', confirm: 'تأیید نهایی و تسویه', 'cancel-financial': 'لغو درخواست', delete: 'حذف درخواست' };
const stages = computed(() => ['create', 'edit'].includes(kind.value) ? ['محل خدمت', 'اطلاعات درخواست', 'بررسی نهایی'] : [labels[kind.value], 'بررسی و ثبت']);
const detailStages = ['ثبت درخواست', 'تخصیص و قیمت', 'تأمین مبلغ', 'اجرای خدمت', 'تأیید و تسویه'];
const currentStage = computed(() => !selected.value ? 0 : selected.value.status === 'completed' ? 5 : selected.value.status === 'awaiting_confirmation' ? 4 : selected.value.status === 'in_progress' ? 3 : activePayment(selected.value) ? 2 : selected.value.assigned_to ? 1 : 0);
const walletSources = computed(() => [
    building.value?.finance.user_wallet && { value: 'user_wallet', label: 'کیف پول شخصی من' },
    building.value?.finance.unit_wallet && props.workspace.units.some(unit => unit.id === selected.value?.unit_id && unit.canUseWallet) && { value: 'unit_wallet', label: 'کیف پول واحد' },
].filter(Boolean) as { value: string; label: string }[]);
const fields = computed<CrudField[]>(() => {
    if (kind.value === 'assign') return [{ name: 'assigned_to', label: 'ارائه‌دهنده', type: 'select', required: true, options: providers.value }];
    if (kind.value === 'quote') return [{ name: 'amount', label: 'مبلغ کل (' + currency.value + ')', type: 'number', min: 1, step: 1, required: true }, { name: 'valid_until', label: 'اعتبار پیشنهاد تا', type: 'datetime-local', help: 'اختیاری؛ زمان باید در آینده باشد.' }, { name: 'notes', label: 'توضیحات پیشنهاد', type: 'textarea', maxlength: 5000 }];
    if (kind.value === 'accept') return [{ name: 'payer_source', label: 'پرداخت از', type: 'select', required: true, options: walletSources.value }];
    return step.value === 0 ? [
        { name: 'building_id', label: 'ساختمان', type: 'select', required: true, readonly_on_edit: true, options: props.workspace.buildings.filter(row => row.canCreate || row.id === selected.value?.building_id).map(row => ({ value: row.id, label: row.title })) },
        { name: 'unit_id', label: 'محل انجام خدمت', type: 'select', options: [{ value: '', label: 'مشاعات ساختمان' }, ...availableUnits.value.map(row => ({ value: row.id, label: row.label }))], help: 'برای خدمات داخل واحد، همان واحد را انتخاب کنید؛ خالی‌بودن یعنی مشاعات.' },
    ] : [
        { name: 'title', label: 'عنوان درخواست', required: true, maxlength: 255, placeholder: 'مثلاً تعمیر روشنایی راه‌پله' },
        { name: 'type', label: 'نوع خدمت', required: true, maxlength: 50, placeholder: 'مثلاً برق، نظافت یا تأسیسات' },
        { name: 'priority', label: 'اولویت', type: 'select', required: true, options: Object.entries(servicePriorities).map(([value, label]) => ({ value, label })) },
        { name: 'description', label: 'شرح درخواست', type: 'textarea', maxlength: 20000, help: 'محل دقیق و جزئیات لازم برای انجام کار را بنویسید.' },
    ];
});
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus(); };
const explain = (caught: unknown) => {
    const translations: Record<string, string> = {
        'Service request cannot be edited in its current lifecycle state.': 'در وضعیت فعلی امکان ویرایش درخواست وجود ندارد؛ اطلاعات را به‌روزرسانی کنید.',
        'Provider cannot be changed after wallet payment has been locked.': 'پس از تأمین مبلغ، ارائه‌دهنده قابل تغییر نیست.',
        'Service financial operations are disabled for this building.': 'عملیات مالی خدمات این ساختمان غیرفعال است.',
        'The service quote has expired.': 'اعتبار پیشنهاد تمام شده است؛ پیشنهاد جدید لازم است.',
        'Insufficient available wallet balance.': 'موجودی قابل استفاده کیف پول کافی نیست.',
        'Service request does not have an authorized locked payment.': 'ابتدا درخواست‌کننده باید پیشنهاد قیمت را بپذیرد و مبلغ را تأمین کند.',
    };
    errors.value = caught instanceof ApiError ? caught.errors : {};
    const messages = Object.values(errors.value).flat();
    error.value = (messages.length ? messages : [caught instanceof Error ? caught.message : 'دریافت اطلاعات انجام نشد. دوباره تلاش کنید.']).map(message => translations[message] || message).join('؛ ');
    void focus('[data-service-error]');
};
const load = async (target = 1) => {
    const version = ++listTicket; loading.value = true; error.value = ''; rows.value = [];
    try {
        const payload = await request<{ data: Record<string, unknown>[]; current_page: number; last_page: number; total: number }>('/api/v1/service-requests?' + new URLSearchParams({ page: String(target), per_page: '20', ...(status.value ? { status: status.value } : {}) }));
        if (disposed || version !== listTicket) return;
        rows.value = payload.data.map(normalizeRequest); page.value = payload.current_page; lastPage.value = payload.last_page; total.value = payload.total; uncertain.value = false;
    } catch (caught) { if (!disposed && version === listTicket) explain(caught); }
    finally { if (version === listTicket) loading.value = false; }
};
const openDetail = async (id: number) => {
    const version = ++detailTicket; loading.value = true; view.value = 'detail'; selected.value = null; error.value = ''; uncertain.value = false;
    try { const payload = await request<{ data: Record<string, unknown> }>('/api/v1/service-requests/' + id); if (!disposed && version === detailTicket) { selected.value = normalizeRequest(payload.data); await focus('#service-detail-title'); } }
    catch (caught) { if (!disposed && version === detailTicket) explain(caught); }
    finally { if (version === detailTicket) loading.value = false; }
};
const back = () => { if (busy.value) return; detailTicket++; view.value = 'list'; loading.value = false; error.value = ''; void load(page.value); void focus('#service-list-title'); };
const loadProviders = async () => {
    const version = ++providerTicket; lookupLoading.value = true; providerError.value = ''; form.value.assigned_to = ''; providers.value = [];
    try { const payload = await request<{ data: { id: number; label: string }[] }>('/management/lookups/users?search=' + encodeURIComponent(providerSearch.value)); if (!disposed && version === providerTicket) providers.value = payload.data.map(row => ({ value: row.id, label: row.label })); }
    catch { if (!disposed && version === providerTicket) providerError.value = 'فهرست کاربران دریافت نشد؛ دوباره جست‌وجو کنید.'; }
    finally { if (version === providerTicket) lookupLoading.value = false; }
};
const canAccept = (item: ServiceQuote) => Boolean(selected.value && item.provider_user_id === selected.value.assigned_to && props.workspace.userId === selected.value.requested_by && !closedRequest(selected.value) && !activePayment(selected.value) && building.value?.finance.active && item.status === 'pending' && (!item.valid_until || new Date(item.valid_until).getTime() > Date.now()) && walletSources.value.length);
const openEditor = async (nextKind = 'create', nextQuote: ServiceQuote | null = null) => {
    if (busy.value || uncertain.value) return;
    if (nextKind === 'create' ? !props.workspace.buildings.some(row => row.canCreate) : nextKind === 'accept' ? !nextQuote || !canAccept(nextQuote) : !actions.value.includes(nextKind)) return;
    if (nextKind === 'create') selected.value = null;
    kind.value = nextKind; quote.value = nextQuote; step.value = 0; errors.value = {}; error.value = ''; success.value = ''; discard.value = false;
    const defaultBuilding = buildingFilter.value || (props.workspace.buildings.filter(row => row.canCreate).length === 1 ? String(props.workspace.buildings.find(row => row.canCreate)!.id) : '');
    form.value = ['create', 'edit'].includes(nextKind) ? nextKind === 'edit' ? { building_id: String(selected.value!.building_id), unit_id: selected.value!.unit_id ? String(selected.value!.unit_id) : '', title: selected.value!.title, type: selected.value!.type, priority: selected.value!.priority, description: selected.value!.description || '' } : { building_id: defaultBuilding, unit_id: '', title: '', type: '', priority: 'normal', description: '' }
        : nextKind === 'quote' ? { amount: '', valid_until: '', notes: '' } : nextKind === 'accept' ? { payer_source: walletSources.value[0]?.value || '' } : { assigned_to: '' };
    view.value = 'editor'; snapshot = JSON.stringify(form.value); providerSearch.value = '';
    if (nextKind === 'assign') await loadProviders();
    await focus('#service-editor-title');
};
const closeEditor = (force = false) => { if (busy.value) return; if (!force && JSON.stringify(form.value) !== snapshot) { discard.value = true; return; } providerTicket++; lookupLoading.value = false; discard.value = false; error.value = ''; view.value = selected.value ? 'detail' : 'list'; void focus(selected.value ? '#service-detail-title' : '#service-list-title'); };
watch(() => form.value.building_id, () => { if (form.value.unit_id && !availableUnits.value.some(row => row.id === Number(form.value.unit_id))) form.value.unit_id = ''; });
const validate = () => {
    const next: Record<string, string[]> = {};
    if (['create', 'edit'].includes(kind.value)) {
        if (!building.value || kind.value === 'create' && !building.value.canCreate) next.building_id = ['ساختمان مجاز را انتخاب کنید.'];
        if (form.value.unit_id && !availableUnits.value.some(unit => unit.id === Number(form.value.unit_id))) next.unit_id = ['واحدی از همین ساختمان انتخاب کنید.'];
        if (step.value > 0) {
            for (const [key, max] of [['title', 255], ['type', 50]] as const) if (!String(form.value[key] || '').trim() || String(form.value[key]).length > max) next[key] = ['این فیلد را با طول مجاز تکمیل کنید.'];
            if (!servicePriorities[String(form.value.priority)]) next.priority = ['اولویت را انتخاب کنید.'];
            if (String(form.value.description || '').length > 20000) next.description = ['شرح درخواست بیش از حد طولانی است.'];
        }
    } else if (kind.value === 'assign' && !providers.value.some(row => row.value === Number(form.value.assigned_to))) next.assigned_to = ['ارائه‌دهنده را از فهرست انتخاب کنید.'];
    else if (kind.value === 'quote') {
        if (!form.value.amount || !Number.isSafeInteger(Number(form.value.amount)) || Number(form.value.amount) < 1) next.amount = ['مبلغ صحیح و مثبت وارد کنید.'];
        if (form.value.valid_until && (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(String(form.value.valid_until)) || new Date(String(form.value.valid_until)).getTime() <= Date.now())) next.valid_until = ['زمان معتبر در آینده انتخاب کنید.'];
        if (String(form.value.notes || '').length > 5000) next.notes = ['یادداشت بیش از حد طولانی است.'];
    } else if (kind.value === 'accept' && (!quote.value || !canAccept(quote.value) || !walletSources.value.some(row => row.value === form.value.payer_source))) next.payer_source = ['پیشنهاد معتبر و کیف پول مجاز انتخاب کنید.'];
    errors.value = next;
    if (!Object.keys(next).length) return true;
    step.value = next.building_id || next.unit_id ? 0 : kind.value === 'create' || kind.value === 'edit' ? 1 : 0;
    error.value = Object.values(next).flat().join('؛ '); void focus('[data-service-error]'); return false;
};
const save = async () => {
    if (busy.value || lookupLoading.value || uncertain.value || !validate()) return;
    error.value = '';
    if (step.value < stages.value.length - 1) { step.value++; await focus('#service-step-title'); return; }
    busy.value = true;
    try {
        const id = selected.value?.id; let savedId = id;
        if (kind.value === 'create' || kind.value === 'edit') {
            const body = { ...form.value, building_id: Number(form.value.building_id), unit_id: form.value.unit_id ? Number(form.value.unit_id) : null, title: String(form.value.title).trim(), type: String(form.value.type).trim() };
            const payload = await request<{ data: { id: number } }>('/api/v1/service-requests' + (id ? '/' + id : ''), id ? 'PATCH' : 'POST', body); savedId = payload.data.id;
        } else if (kind.value === 'assign') await request('/api/v1/service-requests/' + id + '/assign', 'POST', { assigned_to: Number(form.value.assigned_to) });
        else if (kind.value === 'quote') await request('/api/v1/service-requests/' + id + '/quotes', 'POST', { amount: Number(form.value.amount), notes: form.value.notes || null, valid_until: form.value.valid_until || null });
        else await request('/api/v1/service-request-quotes/' + quote.value!.id + '/accept', 'POST', { payer_source: form.value.payer_source });
        snapshot = JSON.stringify(form.value); await openDetail(savedId!); success.value = 'اطلاعات ثبت و وضعیت درخواست به‌روزرسانی شد.';
    } catch (caught) { explain(caught); step.value = ['create', 'edit'].includes(kind.value) && ['title', 'type', 'priority', 'description'].some(key => errors.value[key]) ? 1 : 0; if (!(caught instanceof ApiError) || caught.status >= 500) { uncertain.value = true; error.value += ' نتیجه ثبت مشخص نیست؛ پیش از تکرار، وضعیت درخواست را بررسی کنید.'; } }
    finally { busy.value = false; }
};
const confirmationText = computed(() => confirmation.value === 'confirm' ? 'با تأیید انجام خدمت، مبلغ مسدودشده بین ارائه‌دهنده و سامانه تسویه می‌شود. ادامه می‌دهید؟' : confirmation.value === 'cancel-financial' ? 'درخواست لغو می‌شود و مبلغ مسدودشده، در صورت وجود، آزاد خواهد شد.' : confirmation.value === 'delete' ? 'این درخواست بدون ارائه‌دهنده حذف شود؟' : confirmation.value === 'finish' ? 'انجام کار برای بررسی و تأیید نهایی ارسال شود؟' : 'اجرای این خدمت آغاز شود؟');
const perform = async () => {
    const action = confirmation.value; if (busy.value || uncertain.value || !selected.value || !actions.value.includes(action)) return;
    busy.value = true;
    try { const id = selected.value.id; await request('/api/v1/service-requests/' + id + (action === 'delete' ? '' : '/' + action), action === 'delete' ? 'DELETE' : 'POST', action === 'delete' ? undefined : {}); confirmation.value = ''; if (action === 'delete') { view.value = 'list'; selected.value = null; await load(); } else await openDetail(id); success.value = 'عملیات انجام شد.'; }
    catch (caught) { explain(caught); if (!(caught instanceof ApiError) || caught.status >= 500) uncertain.value = true; }
    finally { busy.value = false; confirmation.value = ''; }
};
const checkSavedState = () => { closeEditor(true); if (selected.value) void openDetail(selected.value.id); else back(); };
const beforeLeave = (event: BeforeUnloadEvent) => { if (busy.value || view.value === 'editor' && JSON.stringify(form.value) !== snapshot) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => { const initial = props.workspace.initial.building_id; if (props.workspace.buildings.some(row => String(row.id) === initial)) buildingFilter.value = initial!; void load(); window.addEventListener('beforeunload', beforeLeave); });
onBeforeUnmount(() => { disposed = true; listTicket++; detailTicket++; providerTicket++; window.removeEventListener('beforeunload', beforeLeave); });
</script>

<template>
    <Head title="درخواست خدمات" />
    <div ref="root" class="ownership-page finance-page service-page" data-service-page>
        <nav class="finance-breadcrumb" aria-label="مسیر صفحه"><a href="/management">داشبورد</a><span>/</span><span>خدمات ساختمان</span><span>/</span><strong>درخواست خدمات</strong></nav>
        <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m14 6 4-4 4 4-4 4M16 8l-5 5M8 3l3 3-5 5-3-3M8 10l12 12M4 20l5-5"/></svg></div><div><h2>درخواست خدمات</h2><p>ثبت، پیگیری و تأیید خدمات ساختمان</p></div></header>
        <div v-if="error" class="ownership-alert ownership-alert--error" role="alert" tabindex="-1" data-service-error>{{ error }}</div><div v-if="success" role="status" class="ownership-alert ownership-alert--success">{{ success }}</div>
        <section v-if="view === 'list'" class="ownership-card" :aria-busy="loading">
            <div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="service-list-title" tabindex="-1">درخواست‌ها</h3><p>{{ money(total) }} درخواست در وضعیت انتخاب‌شده</p></div><div class="finance-actions"><button type="button" class="ownership-button" :disabled="loading" @click="load(page)">به‌روزرسانی</button><button v-if="workspace.buildings.some(row => row.canCreate)" type="button" class="ownership-button ownership-button--primary" data-service-create @click="openEditor()">+ درخواست جدید</button></div></div>
            <div class="finance-filters finance-filters--three"><label class="ownership-field"><span>جست‌وجو در همین صفحه</span><input v-model="search" type="search" placeholder="عنوان، شماره یا ارائه‌دهنده"></label><label class="ownership-field"><span>وضعیت درخواست</span><select v-model="status" data-select2="off" data-service-status @change="load()"><option value="">همه وضعیت‌ها</option><option v-for="(label, value) in serviceStatuses" :key="value" :value="value">{{ label }}</option></select></label><label class="ownership-field"><span>ساختمان در همین صفحه</span><select v-model="buildingFilter" data-select2="off"><option value="">همه ساختمان‌های قابل دسترسی</option><option v-for="item in workspace.buildings" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select></label></div>
            <p v-if="loading" role="status" class="finance-empty">در حال دریافت درخواست‌ها…</p><p v-else-if="!shown.length" class="finance-empty">{{ search || buildingFilter ? 'در این صفحه موردی با فیلتر انتخاب‌شده پیدا نشد.' : 'درخواستی برای نمایش وجود ندارد.' }}</p>
            <div v-else class="service-list"><article v-for="row in shown" :key="row.id" class="service-record"><div class="service-record__main"><div class="service-record__heading"><h4>{{ row.title }}</h4><span class="service-state" :data-state="row.status">{{ serviceStatuses[row.status] || row.status }}</span><span v-if="row.priority !== 'normal'" class="service-priority" :data-priority="row.priority">اولویت {{ servicePriorities[row.priority] }}</span></div><p><bdi>{{ row.request_number }}</bdi> · {{ buildingName(row.building_id) }} · {{ unitName(row.unit_id) }}</p><p>{{ row.type }} · ارائه‌دهنده: {{ personName(row.provider) }}</p></div><button type="button" class="ownership-button" data-service-open :aria-label="'مشاهده درخواست ' + row.title" @click="openDetail(row.id)">مشاهده و پیگیری</button></article></div>
            <nav v-if="lastPage > 1" class="ownership-actions finance-pagination" aria-label="صفحه‌بندی درخواست‌ها"><button type="button" class="ownership-button" :disabled="loading || page <= 1" @click="load(page - 1)">صفحه قبل</button><span>صفحه {{ money(page) }} از {{ money(lastPage) }}</span><button type="button" class="ownership-button" :disabled="loading || page >= lastPage" @click="load(page + 1)">صفحه بعد</button></nav>
        </section>
        <section v-else-if="view === 'detail'" class="ownership-card" :aria-busy="loading"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="service-detail-title" tabindex="-1">{{ selected?.title || 'جزئیات درخواست' }}</h3><p><bdi>{{ selected?.request_number }}</bdi></p></div><div class="finance-actions"><button v-if="selected" type="button" class="ownership-button" :disabled="busy || loading" data-service-refresh @click="openDetail(selected.id)">به‌روزرسانی وضعیت</button><button type="button" class="ownership-button" :disabled="busy" @click="back">بازگشت به درخواست‌ها</button></div></div>
            <p v-if="loading" role="status" class="finance-empty">در حال دریافت جزئیات…</p><template v-else-if="selected">
                <div class="service-detail-status"><span class="service-state" :data-state="selected.status">{{ serviceStatuses[selected.status] }}</span><span>اولویت {{ servicePriorities[selected.priority] }}</span></div>
                <ol v-if="selected.status !== 'cancelled'" class="service-timeline" aria-label="وضعیت فرآیند"><li v-for="(label, index) in detailStages" :key="label" :class="{ 'is-current': currentStage === index, 'is-complete': currentStage > index }" :aria-current="currentStage === index ? 'step' : undefined"><b>{{ index + 1 }}</b><span>{{ label }}</span></li></ol>
                <dl class="finance-review"><dt>ساختمان و محل</dt><dd>{{ buildingName(selected.building_id) }} · {{ unitName(selected.unit_id) }}</dd><dt>نوع خدمت</dt><dd>{{ selected.type }}</dd><dt>درخواست‌کننده</dt><dd>{{ personName(selected.requester) }}</dd><dt>ارائه‌دهنده</dt><dd>{{ personName(selected.provider) }}</dd><dt>زمان ثبت</dt><dd>{{ day(selected.created_at) }}</dd><template v-if="selected.description"><dt>شرح درخواست</dt><dd>{{ selected.description }}</dd></template></dl>
                <div class="ownership-actions service-action-bar"><template v-for="action in actions" :key="action"><button type="button" class="ownership-button" :class="{ 'ownership-button--primary': primaryAction === action, 'finance-danger': ['delete','cancel-financial'].includes(action) }" :data-service-action="action" :disabled="busy || uncertain" @click="['edit','assign','quote'].includes(action) ? openEditor(action) : confirmation = action">{{ labels[action] }}</button></template></div>
                <p v-if="!closedRequest(selected) && !activePayment(selected)" class="finance-hint">برای شروع خدمت، ابتدا ارائه‌دهنده و پیشنهاد قیمت مشخص می‌شوند؛ سپس درخواست‌کننده مبلغ را از کیف پول تأمین می‌کند.</p>
                <section class="finance-detail-section"><h4>پیشنهادهای قیمت</h4><p v-if="!selected.quotes?.length" class="finance-empty">هنوز پیشنهاد قیمتی ثبت نشده است.</p><div v-else class="service-quotes"><article v-for="item in selected.quotes" :key="item.id" class="finance-line"><div class="finance-line__heading"><strong>{{ money(item.amount) }} {{ currency }}</strong><span class="finance-status">{{ item.status === 'pending' && item.valid_until && new Date(item.valid_until).getTime() <= Date.now() ? 'منقضی‌شده' : quoteStatuses[item.status] || item.status }}</span></div><p v-if="item.notes" class="service-notes">{{ item.notes }}</p><p class="finance-hint">سهم ارائه‌دهنده: {{ money(item.provider_amount) }} · کارمزد سامانه: {{ money(item.commission_amount) }} {{ currency }}</p><p class="finance-hint">اعتبار: {{ item.valid_until ? new Date(item.valid_until).toLocaleString('fa-IR') : 'بدون تاریخ پایان' }}</p><button v-if="canAccept(item)" type="button" class="ownership-button ownership-button--primary" :disabled="busy || uncertain" data-service-accept @click="openEditor('accept', item)">بررسی و پذیرش پیشنهاد</button></article></div><p v-if="selected.requested_by !== workspace.userId && selected.quotes?.some(item => item.status === 'pending')" class="finance-hint">پذیرش قیمت و تأمین مبلغ فقط توسط درخواست‌کننده انجام می‌شود.</p></section>
                <section class="finance-detail-section"><h4>وضعیت پرداخت خدمت</h4><p v-if="!selected.wallet_payment" class="finance-empty">هنوز مبلغی برای این خدمت تأمین نشده است.</p><dl v-else class="finance-review"><dt>وضعیت</dt><dd>{{ { locked: 'مبلغ مسدود شده؛ در انتظار انجام و تأیید خدمت', settled: 'تسویه‌شده', released: 'مبلغ آزاد شده' }[selected.wallet_payment.status] || selected.wallet_payment.status }}</dd><dt>مبلغ</dt><dd>{{ money(selected.wallet_payment.amount) }} {{ currency }}</dd><dt>منبع پرداخت</dt><dd>{{ selected.wallet_payment.payer_source === 'unit_wallet' ? 'کیف پول واحد' : 'کیف پول شخصی' }}</dd><dt>سهم ارائه‌دهنده / سامانه</dt><dd>{{ money(selected.wallet_payment.provider_amount) }} / {{ money(selected.wallet_payment.commission_amount) }} {{ currency }}</dd></dl></section>
            </template>
        </section>
        <section v-else-if="view === 'editor'" class="ownership-card finance-editor" :aria-busy="busy"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="service-editor-title" tabindex="-1">{{ labels[kind] }}</h3><p>{{ selected?.title || 'اطلاعات را وارد و پیش از ثبت بررسی کنید.' }}</p></div><button type="button" class="ownership-button" :disabled="busy" @click="closeEditor()">بازگشت</button></div>
            <nav class="ownership-flow" :style="{ '--finance-steps': stages.length }" aria-label="مراحل ثبت"><span v-for="(label, index) in stages" :key="label" :class="{ 'is-current': step === index, 'is-complete': step > index }" :aria-current="step === index ? 'step' : undefined"><b>{{ index + 1 }}</b>{{ label }}</span></nav><h4 id="service-step-title" tabindex="-1">{{ stages[step] }}</h4>
            <form id="service-request-form" novalidate @submit.prevent="save"><fieldset :disabled="busy || uncertain">
                <template v-if="step < stages.length - 1"><div v-if="kind === 'assign'" class="finance-line"><label class="ownership-field"><span>جست‌وجوی ارائه‌دهنده</span><input v-model="providerSearch" type="search" placeholder="نام، شماره موبایل یا ایمیل" @keydown.enter.prevent="loadProviders"></label><div class="ownership-actions"><button type="button" class="ownership-button" :disabled="lookupLoading" data-service-provider-search @click="loadProviders">{{ lookupLoading ? 'در حال جست‌وجو…' : 'جست‌وجو' }}</button></div><p v-if="providerError" role="alert" class="finance-field-error">{{ providerError }}</p><p v-else-if="!lookupLoading && !providers.length" class="finance-hint">کاربری پیدا نشد؛ عبارت جست‌وجو را تغییر دهید.</p></div><p v-if="kind === 'accept'" class="finance-empty">مبلغ {{ money(quote?.amount) }} {{ currency }} تا تأیید نهایی خدمت مسدود می‌شود. ثبت این مرحله به معنای تسویه با ارائه‌دهنده نیست.</p><DynamicForm v-model="form" :fields="fields" :mode="kind === 'edit' ? 'edit' : 'create'" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" :group-optional="false" /></template>
                <template v-else><dl class="finance-review"><dt>ساختمان</dt><dd>{{ building?.title }}</dd><template v-if="['create','edit'].includes(kind)"><dt>محل خدمت</dt><dd>{{ unitName(Number(form.unit_id)) }}</dd><dt>عنوان</dt><dd>{{ form.title }}</dd><dt>نوع / اولویت</dt><dd>{{ form.type }} · {{ servicePriorities[String(form.priority)] }}</dd><template v-if="form.description"><dt>شرح</dt><dd>{{ form.description }}</dd></template></template><template v-else-if="kind === 'assign'"><dt>ارائه‌دهنده</dt><dd>{{ providers.find(item => item.value === Number(form.assigned_to))?.label }}</dd></template><template v-else><dt>مبلغ</dt><dd>{{ money(kind === 'quote' ? form.amount : quote?.amount) }} {{ currency }}</dd><template v-if="kind === 'accept'"><dt>منبع پرداخت</dt><dd>{{ walletSources.find(item => item.value === form.payer_source)?.label }}</dd></template><template v-else><dt>اعتبار پیشنهاد</dt><dd>{{ form.valid_until ? new Date(String(form.valid_until)).toLocaleString('fa-IR') : 'بدون تاریخ پایان' }}</dd><dt>توضیحات</dt><dd>{{ form.notes || '—' }}</dd></template></template></dl><p v-if="kind === 'accept'" class="finance-hint">با ثبت، مبلغ از موجودی قابل استفاده کیف پول کم و تا پایان فرآیند مسدود می‌شود.</p><p v-if="kind === 'quote'" class="finance-hint">کارمزد و سهم ارائه‌دهنده بر اساس تنظیمات مالی ساختمان توسط سامانه محاسبه می‌شوند.</p></template>
            </fieldset><div v-if="discard" class="finance-line" role="alert"><p>تغییرات ذخیره نشده‌اند. از فرم خارج می‌شوید؟</p><div class="finance-actions"><button type="button" class="ownership-button" @click="discard = false">ادامه ویرایش</button><button type="button" class="ownership-button finance-danger" @click="closeEditor(true)">خروج بدون ذخیره</button></div></div><div class="ownership-actions finance-pagination"><button v-if="step > 0" type="button" class="ownership-button" :disabled="busy" @click="step--; error = ''; focus('#service-step-title')">مرحله قبل</button><button v-if="uncertain" type="button" class="ownership-button" @click="checkSavedState">بررسی وضعیت درخواست</button><button type="submit" class="ownership-button ownership-button--primary" data-service-save :disabled="busy || lookupLoading || uncertain">{{ busy ? 'در حال ثبت…' : step === stages.length - 1 ? 'تأیید و ثبت' : 'ادامه' }}</button></div></form>
        </section>
        <UiConfirmDialog :open="Boolean(confirmation)" :title="labels[confirmation] || 'تأیید عملیات'" :message="confirmationText" :busy="busy" confirm-label="تأیید و اجرا" @cancel="!busy && (confirmation = '')" @confirm="perform" />
    </div>
</template>
