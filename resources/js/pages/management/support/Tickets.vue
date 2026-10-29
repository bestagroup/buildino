<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import UiSwitch from '../../../components/management/UiSwitch.vue';
import { ApiError, apiRequest } from '../../../lib/management-api';
import { money } from '../../../lib/invoice-workspace';
import { personName } from '../../../lib/service-requests';
import { normalizeTicket, ticketAbilities, ticketActions, ticketClosed, ticketDateTime, ticketPriorities, ticketStatuses, type SupportTicketRow, type SupportWorkspace, type TicketMessage } from '../../../lib/support-tickets';
import type { CrudField, CrudRecord } from '../../../types/management';
import '../../../../css/finance-workspace.css';
import '../../../../css/support-tickets.css';

const props = defineProps<{ workspace: SupportWorkspace; csrfToken: string }>();
const root = ref<HTMLElement | null>(null), view = ref<'list' | 'detail' | 'editor'>('list');
const rows = ref<SupportTicketRow[]>([]), selected = ref<SupportTicketRow | null>(null), detailId = ref<number | null>(null);
const loading = ref(false), busy = ref(false), uncertain = ref(false), error = ref(''), success = ref('');
const search = ref(''), status = ref(''), priority = ref(''), page = ref(1), lastPage = ref(1), total = ref(0);
const kind = ref('create'), step = ref(0), form = ref<CrudRecord>({}), errors = ref<Record<string, string[]>>({}), discard = ref(false), confirmation = ref('');
const assigneeSearch = ref(''), assignees = ref<{ value: number; label: string }[]>([]), lookupLoading = ref(false), lookupError = ref('');
const messages = ref<TicketMessage[]>([]), message = ref(''), internal = ref(false), messageError = ref(''), historyError = ref(''), historyLoading = ref(false), messagePage = ref(1), messageLastPage = ref(1), discardReply = ref(false);
let listTicket = 0, detailTicket = 0, historyTicket = 0, lookupTicket = 0, disposed = false, snapshot = '';
const request = <T,>(path: string, method = 'GET', body?: unknown) => apiRequest<T>(path, { csrfToken: props.csrfToken, method, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus(); };
const building = computed(() => props.workspace.buildings.find(item => item.id === Number(form.value.building_id)));
const units = computed(() => props.workspace.units.filter(item => item.building_id === Number(form.value.building_id) && (kind.value === 'edit' ? item.canUpdate || item.id === selected.value?.unit_id : item.canCreate)));
const categories = computed(() => props.workspace.categories.filter(item => item.is_active || kind.value === 'edit' && item.id === selected.value?.support_category_id));
const shown = computed(() => rows.value.filter(row => [row.ticket_number, row.subject, row.support_category?.title, personName(row.assignee)].join(' ').toLocaleLowerCase('fa').includes(search.value.trim().toLocaleLowerCase('fa'))));
const actions = computed(() => selected.value ? ticketActions(selected.value, props.workspace) : []);
const canSeeInternal = computed(() => Boolean(selected.value && ticketAbilities(selected.value, props.workspace).update));
const canWriteInternal = computed(() => canSeeInternal.value && selected.value?.user_id !== props.workspace.userId);
const currentMessages = computed(() => messages.value.filter(item => !item.is_internal || canSeeInternal.value));
const labels: Record<string, string> = { create: 'تیکت جدید', edit: 'ویرایش تیکت', assign: 'تخصیص کارشناس', start: 'شروع رسیدگی', 'wait-user': 'در انتظار پاسخ کاربر', resolve: 'ثبت راه‌حل', close: 'بستن تیکت', reopen: 'بازگشایی تیکت', delete: 'حذف تیکت' };
const stages = computed(() => ['create', 'edit'].includes(kind.value) ? ['محدوده و دسته‌بندی', 'موضوع و شرح', 'بررسی نهایی'] : [labels[kind.value], 'بررسی و ثبت']);
const buildingName = (id: number | null) => id ? props.workspace.buildings.find(item => item.id === id)?.title || 'ساختمان ' + money(id) : 'پشتیبانی عمومی';
const unitName = (id: number | null) => id ? props.workspace.units.find(item => item.id === id)?.label || 'واحد ' + money(id) : 'بدون واحد مشخص';
const fields = computed<CrudField[]>(() => kind.value === 'assign' ? [{ name: 'assigned_to', label: 'کارشناس', type: 'select', required: true, options: assignees.value }] : kind.value === 'resolve' ? [{ name: 'resolution', label: 'شرح راه‌حل', type: 'textarea', required: true, maxlength: 20000, help: 'اقدام انجام‌شده و نتیجه را برای کاربر توضیح دهید.' }] : step.value === 0 ? [{ name: 'support_category_id', label: 'دسته‌بندی', type: 'select', options: categories.value.map(item => ({ value: item.id, label: item.title + (item.is_active ? '' : ' (غیرفعال)') })) }] : [
    { name: 'subject', label: 'موضوع تیکت', required: true, maxlength: 255, placeholder: 'موضوع را کوتاه و مشخص بنویسید' },
    { name: 'priority', label: 'اولویت', type: 'select', required: true, options: Object.entries(ticketPriorities).map(([value, label]) => ({ value, label })) },
    { name: 'description', label: 'شرح درخواست', type: 'textarea', required: true, maxlength: 20000, help: 'مشکل، محل وقوع و جزئیات لازم برای بررسی را بنویسید.' },
]);
const overdue = (deadline?: string | null, completed?: string | null) => Boolean(selected.value && !ticketClosed(selected.value) && !completed && deadline && new Date(deadline).getTime() < Date.now());
const deadlineHint = (deadline?: string | null, completed?: string | null, response = false) => completed ? (response ? 'پاسخ ثبت شده: ' : 'حل شده: ') + ticketDateTime(completed) : !deadline ? 'مهلتی برای این تیکت ثبت نشده است' : overdue(deadline, completed) ? (response ? 'مهلت پاسخ گذشته است' : 'مهلت حل گذشته است') : 'زمان تعیین‌شده توسط سامانه';
const report = (caught: unknown) => {
    const translations: Record<string, string> = {
        'Resolved/closed tickets must be reopened before editing.': 'پیش از ویرایش، تیکت حل‌شده یا بسته‌شده را بازگشایی کنید.',
        'Resolved/closed tickets must be reopened before adding messages.': 'برای ارسال پیام، ابتدا تیکت را بازگشایی کنید.',
        'Ticket requester cannot create internal support notes.': 'درخواست‌کننده نمی‌تواند یادداشت داخلی ثبت کند.',
        'Assignee must be an active, unblocked user.': 'کارشناس باید فعال و مجاز به استفاده از سامانه باشد.',
        'Only a resolved ticket can be closed.': 'فقط تیکت حل‌شده قابل بستن است.',
    };
    errors.value = caught instanceof ApiError ? caught.errors : {};
    const messages = Object.values(errors.value).flat();
    error.value = (messages.length ? messages : [caught instanceof Error ? caught.message : 'اطلاعات دریافت نشد؛ دوباره تلاش کنید.']).map(item => translations[item] || item).join('؛ ');
    void focus('[data-ticket-error]');
};
const unknownOutcome = (caught: unknown) => !(caught instanceof ApiError) || caught.status >= 500;
const load = async (target = 1) => {
    const version = ++listTicket; loading.value = true; rows.value = []; error.value = '';
    try {
        const payload = await request<{ data: Record<string, unknown>[]; current_page: number; last_page: number; total: number }>('/api/v1/support-tickets?' + new URLSearchParams({ page: String(target), per_page: '20', ...(status.value ? { status: status.value } : {}), ...(priority.value ? { priority: priority.value } : {}) }));
        if (disposed || version !== listTicket) return;
        rows.value = payload.data.map(normalizeTicket); page.value = payload.current_page; lastPage.value = payload.last_page; total.value = payload.total; uncertain.value = false;
    } catch (caught) { if (!disposed && version === listTicket) report(caught); }
    finally { if (version === listTicket) loading.value = false; }
};
const loadMessages = async (target: number | 'latest' = 'latest') => {
    if (!selected.value) return;
    const id = selected.value.id, version = ++historyTicket; historyLoading.value = true; historyError.value = ''; messages.value = [];
    try {
        type Payload = { data: TicketMessage[]; current_page: number; last_page: number };
        let payload = await request<Payload>('/api/v1/support-tickets/' + id + '/messages?per_page=50&page=' + (target === 'latest' ? 1 : target));
        if (disposed || version !== historyTicket) return;
        if (target === 'latest' && payload.last_page > 1) payload = await request<Payload>('/api/v1/support-tickets/' + id + '/messages?per_page=50&page=' + payload.last_page);
        if (disposed || version !== historyTicket || selected.value?.id !== id) return;
        messages.value = payload.data; messagePage.value = payload.current_page; messageLastPage.value = payload.last_page;
    } catch { if (!disposed && version === historyTicket) historyError.value = 'تاریخچه پیام‌ها دریافت نشد. دوباره تلاش کنید.'; }
    finally { if (version === historyTicket) historyLoading.value = false; }
};
const openDetail = async (id: number) => {
    const version = ++detailTicket; historyTicket++; view.value = 'detail'; loading.value = true; selected.value = null; messages.value = []; historyError.value = ''; error.value = ''; success.value = '';
    if (detailId.value !== id) { message.value = ''; internal.value = false; }
    detailId.value = id;
    try {
        const payload = await request<{ data: Record<string, unknown> }>('/api/v1/support-tickets/' + id);
        if (disposed || version !== detailTicket) return;
        selected.value = normalizeTicket(payload.data); uncertain.value = false; if (!canWriteInternal.value) internal.value = false;
        loading.value = false; await focus('#ticket-detail-title'); await loadMessages();
    } catch (caught) { if (!disposed && version === detailTicket) report(caught); }
    finally { if (version === detailTicket) loading.value = false; }
};
const back = (force = false) => { if (busy.value) return; if (!force && message.value.trim()) { discardReply.value = true; void focus('[data-ticket-discard-reply]'); return; } detailTicket++; historyTicket++; message.value = ''; discardReply.value = false; view.value = 'list'; selected.value = null; detailId.value = null; void load(page.value); void focus('#ticket-list-title'); };
const loadAssignees = async () => {
    const version = ++lookupTicket; lookupLoading.value = true; lookupError.value = ''; assignees.value = []; form.value.assigned_to = '';
    try { const payload = await request<{ data: { id: number; label: string }[] }>('/management/lookups/users?search=' + encodeURIComponent(assigneeSearch.value)); if (!disposed && version === lookupTicket) assignees.value = payload.data.map(item => ({ value: item.id, label: item.label })); }
    catch { if (!disposed && version === lookupTicket) lookupError.value = 'فهرست کارشناسان دریافت نشد؛ دوباره جست‌وجو کنید.'; }
    finally { if (version === lookupTicket) lookupLoading.value = false; }
};
const openEditor = async (nextKind = 'create') => {
    if (busy.value || uncertain.value || (nextKind === 'create' ? !props.workspace.canCreate : !actions.value.includes(nextKind))) return;
    if (nextKind === 'create') { selected.value = null; detailId.value = null; }
    kind.value = nextKind; step.value = 0; discard.value = false; error.value = ''; errors.value = {}; success.value = ''; assigneeSearch.value = '';
    const initialBuilding = props.workspace.buildings.find(item => String(item.id) === props.workspace.initial.building_id && item.canCreate);
    const initialUnit = props.workspace.units.find(item => String(item.id) === props.workspace.initial.unit_id && item.building_id === initialBuilding?.id && item.canCreate);
    form.value = nextKind === 'edit' ? { building_id: selected.value!.building_id ? String(selected.value!.building_id) : '', unit_id: selected.value!.unit_id ? String(selected.value!.unit_id) : '', support_category_id: selected.value!.support_category_id ? String(selected.value!.support_category_id) : '', subject: selected.value!.subject, priority: selected.value!.priority, description: selected.value!.description }
        : nextKind === 'create' ? { building_id: initialBuilding ? String(initialBuilding.id) : '', unit_id: initialUnit ? String(initialUnit.id) : '', support_category_id: '', subject: '', priority: 'medium', description: '' }
        : nextKind === 'assign' ? { assigned_to: '' } : { resolution: '' };
    view.value = 'editor'; snapshot = JSON.stringify(form.value);
    if (nextKind === 'assign') await loadAssignees(); await focus('#ticket-editor-title');
};
const closeEditor = (force = false) => { if (busy.value) return; if (!force && JSON.stringify(form.value) !== snapshot) { discard.value = true; return; } lookupTicket++; lookupLoading.value = false; discard.value = false; error.value = ''; view.value = selected.value ? 'detail' : 'list'; void focus(selected.value ? '#ticket-detail-title' : '#ticket-list-title'); };
watch(() => form.value.building_id, () => { if (kind.value !== 'edit' && form.value.unit_id && !units.value.some(item => item.id === Number(form.value.unit_id))) form.value.unit_id = ''; });
const validate = () => {
    const next: Record<string, string[]> = {};
    if (kind.value === 'assign') { if (!assignees.value.some(item => item.value === Number(form.value.assigned_to))) next.assigned_to = ['کارشناس را از فهرست انتخاب کنید.']; }
    else if (kind.value === 'resolve') { if (!String(form.value.resolution || '').trim() || String(form.value.resolution).length > 20000) next.resolution = ['شرح راه‌حل را وارد کنید (حداکثر ۲۰۰۰۰ نویسه).']; }
    else {
        if (kind.value === 'create' && form.value.building_id && !building.value?.canCreate) next.building_id = ['ساختمان مجاز را انتخاب کنید.'];
        if (kind.value === 'create' && form.value.unit_id && !units.value.some(item => item.id === Number(form.value.unit_id))) next.unit_id = ['واحدی از همین ساختمان انتخاب کنید.'];
        if (form.value.support_category_id && !categories.value.some(item => item.id === Number(form.value.support_category_id))) next.support_category_id = ['دسته‌بندی معتبر انتخاب کنید.'];
        if (step.value > 0) {
            for (const [key, max] of [['subject', 255], ['description', 20000]] as const) if (!String(form.value[key] || '').trim() || String(form.value[key]).length > max) next[key] = ['این فیلد را با طول مجاز تکمیل کنید.'];
            if (!ticketPriorities[String(form.value.priority)]) next.priority = ['اولویت را انتخاب کنید.'];
        }
    }
    errors.value = next; if (!Object.keys(next).length) return true;
    step.value = kind.value === 'create' || kind.value === 'edit' ? ['building_id', 'unit_id', 'support_category_id'].some(key => next[key]) ? 0 : 1 : 0;
    error.value = Object.values(next).flat().join('؛ '); void focus('[data-ticket-error]'); return false;
};
const save = async () => {
    if (busy.value || lookupLoading.value || uncertain.value || !validate()) return;
    error.value = ''; if (step.value < stages.value.length - 1) { step.value++; await focus('#ticket-step-title'); return; }
    busy.value = true;
    try {
        let id = selected.value?.id;
        if (kind.value === 'create' || kind.value === 'edit') {
            const body = { subject: String(form.value.subject).trim(), description: String(form.value.description).trim(), priority: form.value.priority, support_category_id: form.value.support_category_id ? Number(form.value.support_category_id) : null,
                ...(kind.value === 'create' ? { building_id: form.value.building_id ? Number(form.value.building_id) : null, unit_id: form.value.unit_id ? Number(form.value.unit_id) : null } : {}) };
            const payload = await request<{ data: { id: number } }>('/api/v1/support-tickets' + (id ? '/' + id : ''), id ? 'PATCH' : 'POST', body); id = payload.data.id;
        } else await request('/api/v1/support-tickets/' + id + '/' + (kind.value === 'assign' ? 'assign' : 'resolve'), 'POST', kind.value === 'assign' ? { assigned_to: Number(form.value.assigned_to) } : { resolution: String(form.value.resolution).trim() });
        snapshot = JSON.stringify(form.value); await openDetail(id!); success.value = kind.value === 'resolve' ? 'راه‌حل ثبت شد و تیکت در وضعیت حل‌شده قرار گرفت.' : 'اطلاعات تیکت ثبت شد.';
    } catch (caught) { report(caught); step.value = ['create', 'edit'].includes(kind.value) && ['subject', 'description', 'priority'].some(key => errors.value[key]) ? 1 : 0; if (unknownOutcome(caught)) { uncertain.value = true; error.value += ' نتیجه ثبت مشخص نیست؛ پیش از تکرار، وضعیت تیکت را بررسی کنید.'; } }
    finally { busy.value = false; }
};
const sendMessage = async () => {
    if (busy.value || uncertain.value || !selected.value || ticketClosed(selected.value)) return;
    if (!message.value.trim() || message.value.length > 20000) { messageError.value = 'پیام را وارد کنید (حداکثر ۲۰۰۰۰ نویسه).'; await focus('#ticket-reply'); return; }
    const id = selected.value.id; busy.value = true; messageError.value = ''; error.value = ''; success.value = '';
    try { await request('/api/v1/support-tickets/' + id + '/messages', 'POST', { message: message.value.trim(), is_internal: internal.value && canWriteInternal.value }); message.value = ''; internal.value = false; await openDetail(id); success.value = 'پیام ارسال شد.'; }
    catch (caught) { messageError.value = caught instanceof ApiError ? Object.values(caught.errors).flat().join('؛ ') || caught.message : 'ارسال پیام انجام نشد.'; if (unknownOutcome(caught)) { uncertain.value = true; messageError.value += ' نتیجه ارسال مشخص نیست؛ تاریخچه را بررسی کنید و فقط در صورت ثبت‌نشدن، دوباره ارسال کنید.'; } }
    finally { busy.value = false; }
};
const confirmationText = computed(() => confirmation.value === 'delete' ? 'تیکت و دسترسی به سابقه آن حذف شود؟' : confirmation.value === 'close' ? 'تیکت حل‌شده بسته شود؟ برای ادامه گفت‌وگو، بازگشایی لازم خواهد بود.' : confirmation.value === 'reopen' ? 'تیکت دوباره برای پیگیری باز شود؟ مهلت حل بر اساس قواعد فعلی محاسبه می‌شود.' : confirmation.value === 'wait-user' ? 'تیکت در انتظار پاسخ کاربر قرار گیرد؟' : 'رسیدگی به این تیکت آغاز شود؟');
const perform = async () => {
    const action = confirmation.value; if (busy.value || uncertain.value || !selected.value || !actions.value.includes(action)) return;
    const id = selected.value.id; busy.value = true;
    try { await request('/api/v1/support-tickets/' + id + (action === 'delete' ? '' : '/' + action), action === 'delete' ? 'DELETE' : 'POST', action === 'delete' ? undefined : {}); confirmation.value = ''; if (action === 'delete') { detailId.value = null; selected.value = null; message.value = ''; view.value = 'list'; await load(); } else await openDetail(id); success.value = 'وضعیت تیکت به‌روزرسانی شد.'; }
    catch (caught) { report(caught); if (unknownOutcome(caught)) uncertain.value = true; }
    finally { busy.value = false; confirmation.value = ''; }
};
const checkSavedState = () => { closeEditor(true); if (detailId.value) void openDetail(detailId.value); else back(true); };
const beforeLeave = (event: BeforeUnloadEvent) => { if (busy.value || message.value.trim() || view.value === 'editor' && JSON.stringify(form.value) !== snapshot) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => { void load(); window.addEventListener('beforeunload', beforeLeave); });
onBeforeUnmount(() => { disposed = true; listTicket++; detailTicket++; historyTicket++; lookupTicket++; window.removeEventListener('beforeunload', beforeLeave); });
</script>

<template>
    <Head title="تیکت‌های پشتیبانی" />
    <div ref="root" class="ownership-page finance-page ticket-page" data-ticket-page>
        <nav class="finance-breadcrumb" aria-label="مسیر صفحه"><a href="/management">داشبورد</a><span>/</span><strong>تیکت‌های پشتیبانی</strong></nav>
        <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 13v-2a8 8 0 0 1 16 0v2M4 12H3v6h4v-6H4Zm16 0h1v6h-4v-6h3Zm0 6a4 4 0 0 1-4 4h-4"/></svg></div><div><h2>تیکت‌های پشتیبانی</h2><p>ثبت درخواست، گفت‌وگو و پیگیری تا حل مشکل</p></div></header>
        <div v-if="error" role="alert" tabindex="-1" class="ownership-alert ownership-alert--error" data-ticket-error>{{ error }}</div><div v-if="success" role="status" class="ownership-alert ownership-alert--success">{{ success }}</div>
        <section v-if="view === 'list'" class="ownership-card" :aria-busy="loading"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="ticket-list-title" tabindex="-1">تیکت‌ها</h3><p>{{ money(total) }} تیکت با فیلتر انتخاب‌شده</p></div><div class="finance-actions"><a v-if="workspace.canManageCategories" class="ownership-button" href="/management/operations/support-categories">دسته‌بندی‌ها</a><button type="button" class="ownership-button" :disabled="loading" @click="load(page)">به‌روزرسانی</button><button v-if="workspace.canCreate" type="button" class="ownership-button ownership-button--primary" data-ticket-create @click="openEditor()">+ تیکت جدید</button></div></div>
            <div class="finance-filters finance-filters--three"><label class="ownership-field"><span>جست‌وجو در همین صفحه</span><input v-model="search" type="search" placeholder="موضوع، شماره، دسته یا کارشناس"></label><label class="ownership-field"><span>وضعیت</span><select v-model="status" data-ticket-status data-select2="off" @change="load()"><option value="">همه وضعیت‌ها</option><option v-for="(label, value) in ticketStatuses" :key="value" :value="value">{{ label }}</option></select></label><label class="ownership-field"><span>اولویت</span><select v-model="priority" data-ticket-priority data-select2="off" @change="load()"><option value="">همه اولویت‌ها</option><option v-for="(label, value) in ticketPriorities" :key="value" :value="value">{{ label }}</option></select></label></div>
            <p v-if="loading" role="status" class="finance-empty">در حال دریافت تیکت‌ها…</p><p v-else-if="!shown.length" class="finance-empty">{{ search ? 'در این صفحه موردی پیدا نشد.' : 'تیکتی با این فیلترها وجود ندارد.' }}</p><div v-else class="ticket-list"><article v-for="row in shown" :key="row.id" class="ticket-record"><div><div class="ticket-record__heading"><h4>{{ row.subject }}</h4><span class="ticket-state" :data-state="row.status">{{ ticketStatuses[row.status] }}</span><span v-if="row.priority !== 'medium'" class="ticket-priority" :data-priority="row.priority">{{ ticketPriorities[row.priority] }}</span></div><p><bdi>{{ row.ticket_number }}</bdi> · {{ buildingName(row.building_id) }} · {{ row.support_category?.title || 'بدون دسته‌بندی' }}</p><p>کارشناس: {{ personName(row.assignee) }} · {{ ticketDateTime(row.created_at) }}</p></div><button type="button" class="ownership-button" data-ticket-open :aria-label="'پیگیری تیکت ' + row.subject" @click="openDetail(row.id)">مشاهده و پیگیری</button></article></div>
            <nav v-if="lastPage > 1" class="ownership-actions finance-pagination" aria-label="صفحه‌بندی تیکت‌ها"><button type="button" class="ownership-button" :disabled="loading || page <= 1" @click="load(page - 1)">صفحه قبل</button><span>صفحه {{ money(page) }} از {{ money(lastPage) }}</span><button type="button" class="ownership-button" :disabled="loading || page >= lastPage" @click="load(page + 1)">صفحه بعد</button></nav>
        </section>
        <section v-else-if="view === 'detail'" class="ownership-card" :aria-busy="loading"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="ticket-detail-title" tabindex="-1">{{ selected?.subject || 'جزئیات تیکت' }}</h3><p><bdi>{{ selected?.ticket_number }}</bdi></p></div><div class="finance-actions"><button v-if="detailId" type="button" class="ownership-button" data-ticket-refresh :disabled="busy || loading" @click="openDetail(detailId)">به‌روزرسانی تیکت</button><button type="button" class="ownership-button" :disabled="busy" @click="back()">بازگشت به تیکت‌ها</button></div></div>
            <p v-if="loading" role="status" class="finance-empty">در حال دریافت جزئیات…</p><template v-else-if="selected"><div class="ticket-detail-status"><span class="ticket-state" :data-state="selected.status">{{ ticketStatuses[selected.status] }}</span><span>اولویت {{ ticketPriorities[selected.priority] }}</span><span>{{ buildingName(selected.building_id) }}</span></div>
                <div class="ticket-sla"><div :class="{ 'is-overdue': overdue(selected.response_due_at, selected.first_response_at) }"><span>مهلت اولین پاسخ</span><strong>{{ ticketDateTime(selected.response_due_at) }}</strong><small>{{ deadlineHint(selected.response_due_at, selected.first_response_at, true) }}</small></div><div :class="{ 'is-overdue': overdue(selected.resolution_due_at, selected.resolved_at) }"><span>مهلت حل تیکت</span><strong>{{ ticketDateTime(selected.resolution_due_at) }}</strong><small>{{ deadlineHint(selected.resolution_due_at, selected.resolved_at) }}</small></div></div>
                <div v-if="actions.length" class="ownership-actions ticket-actions"><button v-for="action in actions" :key="action" type="button" class="ownership-button" :class="{ 'ownership-button--primary': action === (actions.includes('start') ? 'start' : actions.includes('resolve') ? 'resolve' : 'reopen'), 'finance-danger': action === 'delete' }" :data-ticket-action="action" :disabled="busy || uncertain" @click="['edit','assign','resolve'].includes(action) ? openEditor(action) : confirmation = action">{{ labels[action] }}</button></div>
                <div class="ticket-detail-grid"><section class="ticket-conversation" aria-labelledby="ticket-conversation-title"><div class="ticket-section-heading"><h4 id="ticket-conversation-title">گفت‌وگو</h4><button type="button" class="ownership-button" :disabled="historyLoading" @click="loadMessages()">آخرین پیام‌ها</button></div>
                    <article class="ticket-original"><span>شرح اولیه درخواست</span><p>{{ selected.description }}</p></article><section v-if="selected.resolution" class="ticket-resolution"><h5>راه‌حل ثبت‌شده</h5><p>{{ selected.resolution }}</p></section>
                    <p v-if="historyLoading" role="status" class="finance-empty">در حال دریافت پیام‌ها…</p><div v-else-if="historyError" class="finance-empty" role="alert">{{ historyError }} <button type="button" class="ownership-button" data-ticket-history-retry @click="loadMessages()">تلاش دوباره</button></div><p v-else-if="!currentMessages.length" class="finance-hint">هنوز پیامی در این تیکت ثبت نشده است.</p><div v-else class="ticket-messages"><article v-for="item in currentMessages" :key="item.id" class="ticket-message" :class="{ 'is-own': item.user_id === workspace.userId, 'is-internal': item.is_internal }" :data-ticket-message="item.id"><header><strong>{{ personName(item.user) }}</strong><span v-if="item.is_internal" class="ticket-internal-tag">یادداشت داخلی</span><time :datetime="item.created_at">{{ ticketDateTime(item.created_at) }}</time></header><p>{{ item.message }}</p></article></div>
                    <nav v-if="!historyLoading && !historyError && messageLastPage > 1" class="ownership-actions finance-pagination" aria-label="صفحه‌بندی پیام‌ها"><button type="button" class="ownership-button" :disabled="messagePage <= 1" data-ticket-messages-prev @click="loadMessages(messagePage - 1)">پیام‌های قدیمی‌تر</button><span>{{ money(messagePage) }} از {{ money(messageLastPage) }}</span><button type="button" class="ownership-button" :disabled="messagePage >= messageLastPage" @click="loadMessages(messagePage + 1)">پیام‌های جدیدتر</button></nav>
                    <form v-if="!ticketClosed(selected)" class="ticket-composer" :class="{ 'is-internal': internal }" id="ticket-message-form" novalidate @submit.prevent="sendMessage"><fieldset :disabled="busy || uncertain"><label class="ownership-field"><span>{{ internal ? 'یادداشت داخلی برای تیم پشتیبانی' : 'پیام جدید' }}</span><textarea id="ticket-reply" v-model="message" name="message" rows="4" maxlength="20000" :aria-invalid="Boolean(messageError)" :aria-describedby="messageError ? 'ticket-message-error' : undefined" placeholder="پیام خود را بنویسید…"></textarea><small v-if="messageError" id="ticket-message-error" role="alert" class="finance-field-error">{{ messageError }}</small></label><div class="ticket-composer__actions"><UiSwitch v-if="canWriteInternal" v-model="internal" name="is_internal" label="یادداشت داخلی" on-label="داخلی" off-label="عمومی" /><button type="submit" class="ownership-button ownership-button--primary" data-ticket-send :disabled="busy || uncertain">{{ busy ? 'در حال ثبت…' : internal ? 'ثبت یادداشت داخلی' : 'ارسال پیام' }}</button></div><p v-if="internal" class="finance-hint">این یادداشت فقط برای کاربران دارای دسترسی داخلی قابل مشاهده است.</p></fieldset></form>
                    <p v-else class="finance-empty">گفت‌وگو در تیکت حل‌شده یا بسته‌شده متوقف است. {{ actions.includes('reopen') ? 'برای ادامه، تیکت را بازگشایی کنید.' : 'برای ادامه پیگیری با پشتیبانی هماهنگ کنید.' }}</p>
                </section><aside class="ticket-info"><h4>اطلاعات تیکت</h4><dl><dt>محل</dt><dd>{{ buildingName(selected.building_id) }}<br>{{ unitName(selected.unit_id) }}</dd><dt>دسته‌بندی</dt><dd>{{ selected.support_category?.title || 'بدون دسته‌بندی' }}</dd><dt>کارشناس</dt><dd>{{ personName(selected.assignee) }}</dd><dt>زمان ثبت</dt><dd>{{ ticketDateTime(selected.created_at) }}</dd><template v-if="selected.closed_at"><dt>زمان بستن</dt><dd>{{ ticketDateTime(selected.closed_at) }}</dd></template></dl><p v-if="canSeeInternal" class="finance-hint">یادداشت‌های داخلی در گفت‌وگو با برچسب مشخص نمایش داده می‌شوند.</p></aside></div>
                <div v-if="discardReply" class="finance-line" role="alert" tabindex="-1" data-ticket-discard-reply><p>پیام نوشته‌شده هنوز ارسال نشده است. خارج می‌شوید؟</p><div class="finance-actions"><button type="button" class="ownership-button" @click="discardReply = false">ادامه نوشتن</button><button type="button" class="ownership-button finance-danger" @click="back(true)">حذف پیش‌نویس و بازگشت</button></div></div>
            </template>
        </section>
        <section v-else-if="view === 'editor'" class="ownership-card finance-editor" :aria-busy="busy"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="ticket-editor-title" tabindex="-1">{{ labels[kind] }}</h3><p>{{ selected?.subject || 'اطلاعات درخواست را وارد کنید.' }}</p></div><button type="button" class="ownership-button" :disabled="busy" @click="closeEditor()">بازگشت</button></div><nav class="ownership-flow" :style="{ '--finance-steps': stages.length }" aria-label="مراحل ثبت"><span v-for="(label, index) in stages" :key="label" :class="{ 'is-current': index === step, 'is-complete': index < step }" :aria-current="index === step ? 'step' : undefined"><b>{{ index + 1 }}</b>{{ label }}</span></nav><h4 id="ticket-step-title" tabindex="-1">{{ stages[step] }}</h4>
            <form id="support-ticket-form" novalidate @submit.prevent="save"><fieldset :disabled="busy || uncertain"><template v-if="step < stages.length - 1"><div v-if="['create','edit'].includes(kind) && step === 0" class="finance-grid ticket-context"><label class="ownership-field"><span>محدوده تیکت</span><select v-model="form.building_id" name="building_id" data-select2="off" :disabled="kind === 'edit'"><option value="">پشتیبانی عمومی / بدون ساختمان</option><option v-for="item in workspace.buildings.filter(item => item.canCreate || item.id === selected?.building_id)" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select><small class="finance-field-error" v-if="errors.building_id">{{ errors.building_id[0] }}</small></label><label class="ownership-field"><span>واحد</span><select v-model="form.unit_id" name="unit_id" data-select2="off" :disabled="!form.building_id || kind === 'edit'"><option value="">بدون واحد مشخص / مشاعات</option><option v-for="item in units" :key="item.id" :value="String(item.id)">{{ item.label }}</option></select></label></div>
                <div v-if="kind === 'assign'" class="finance-line"><label class="ownership-field"><span>جست‌وجوی کارشناس</span><input v-model="assigneeSearch" type="search" placeholder="نام، موبایل یا ایمیل" @keydown.enter.prevent="loadAssignees"></label><div class="ownership-actions"><button type="button" class="ownership-button" :disabled="lookupLoading" data-ticket-assignee-search @click="loadAssignees">{{ lookupLoading ? 'در حال جست‌وجو…' : 'جست‌وجو' }}</button></div><p v-if="lookupError" role="alert" class="finance-field-error">{{ lookupError }}</p><p v-else-if="!lookupLoading && !assignees.length" class="finance-hint">کاربری پیدا نشد؛ عبارت دیگری جست‌وجو کنید.</p></div><DynamicForm v-model="form" :fields="fields" :mode="kind === 'edit' ? 'edit' : 'create'" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" :group-optional="false" /><p v-if="['create','edit'].includes(kind) && step === 0 && !categories.length" class="finance-hint">دسته‌بندی فعالی وجود ندارد؛ می‌توانید تیکت را بدون دسته‌بندی ثبت کنید.</p></template>
                <template v-else><dl class="finance-review"><template v-if="['create','edit'].includes(kind)"><dt>محدوده</dt><dd>{{ buildingName(Number(form.building_id) || null) }} · {{ unitName(Number(form.unit_id) || null) }}</dd><dt>دسته‌بندی</dt><dd>{{ categories.find(item => item.id === Number(form.support_category_id))?.title || 'بدون دسته‌بندی' }}</dd><dt>موضوع</dt><dd>{{ form.subject }}</dd><dt>اولویت</dt><dd>{{ ticketPriorities[String(form.priority)] }}</dd><dt>شرح</dt><dd>{{ form.description }}</dd></template><template v-else-if="kind === 'assign'"><dt>تیکت</dt><dd>{{ selected?.subject }}</dd><dt>کارشناس</dt><dd>{{ assignees.find(item => item.value === Number(form.assigned_to))?.label }}</dd></template><template v-else><dt>تیکت</dt><dd>{{ selected?.subject }}</dd><dt>شرح راه‌حل</dt><dd>{{ form.resolution }}</dd></template></dl><p v-if="kind === 'resolve'" class="finance-hint">با ثبت راه‌حل، تیکت حل‌شده و گفت‌وگو متوقف می‌شود. بستن تیکت اقدام جداگانه‌ای است.</p></template></fieldset>
                <div v-if="discard" class="finance-line" role="alert"><p>تغییرات ذخیره نشده‌اند. خارج می‌شوید؟</p><div class="finance-actions"><button type="button" class="ownership-button" @click="discard = false">ادامه ویرایش</button><button type="button" class="ownership-button finance-danger" @click="closeEditor(true)">خروج بدون ذخیره</button></div></div><div class="ownership-actions finance-pagination"><button v-if="step > 0" type="button" class="ownership-button" :disabled="busy" @click="step--; error = ''; focus('#ticket-step-title')">مرحله قبل</button><button v-if="uncertain" type="button" class="ownership-button" @click="checkSavedState">بررسی وضعیت تیکت</button><button type="submit" class="ownership-button ownership-button--primary" :disabled="busy || lookupLoading || uncertain" data-ticket-save>{{ busy ? 'در حال ثبت…' : step === stages.length - 1 ? 'تأیید و ثبت' : 'ادامه' }}</button></div></form>
        </section>
        <UiConfirmDialog :open="Boolean(confirmation)" :title="labels[confirmation] || 'تأیید عملیات'" :message="confirmationText" :busy="busy" confirm-label="تأیید و اجرا" @cancel="!busy && (confirmation = '')" @confirm="perform" />
    </div>
</template>
