<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import FinanceFrame from '../../../components/management/FinanceFrame.vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiDateInput from '../../../components/management/UiDateInput.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import { ApiError, apiRequest } from '../../../lib/management-api';
import { canPlan, day, invoiceLabels, invoiceTotal, itemTotal, money, payable, paymentMethods, splitInstallments, today, type Installment, type Invoice, type InvoiceItem, type InvoiceResource, type InvoiceWorkspace, type Payment } from '../../../lib/invoice-workspace';
import type { CrudField, CrudRecord } from '../../../types/management';

const props = defineProps<{ resourceKey: InvoiceResource; title: string; workspace: InvoiceWorkspace; csrfToken: string }>();
const root = ref<HTMLElement | null>(null), buildingId = ref(''), unitId = ref(''), search = ref(''), status = ref('');
const view = ref<'list' | 'detail' | 'editor'>('list'), loading = ref(false), detailLoading = ref(false), busy = ref(false);
const error = ref(''), success = ref(''), errors = ref<Record<string, string[]>>({});
const invoiceRows = ref<Invoice[]>([]), paymentRows = ref<Payment[]>([]), selected = ref<Invoice | null>(null), selectedPayment = ref<Payment | null>(null);
const page = ref(1), lastPage = ref(1), total = ref(0), editorKind = ref<'invoice' | 'plan' | 'payment' | 'penalty'>('invoice'), step = ref(0);
const form = ref<CrudRecord>({}), items = ref<InvoiceItem[]>([]), plan = ref<Installment[]>([]), editingId = ref<number | null>(null), discard = ref(false), uncertain = ref(false);
const planCount = ref(3), firstDue = ref(today()), choices = ref<Invoice[]>([]), choicePage = ref(1), choiceLastPage = ref(1), choiceId = ref(''), choiceLoading = ref(false);
const confirmation = ref<'issue' | 'void' | 'cancel-plan' | 'verify' | null>(null);
let listTicket = 0, detailTicket = 0, choiceTicket = 0, closed = false, snapshot = '', paymentKey = '';
const building = computed(() => props.workspace.buildings.find(item => String(item.id) === buildingId.value));
const units = computed(() => props.workspace.units.filter(item => String(item.building_id) === buildingId.value));
const currency = computed(() => ({ IRR: 'ریال', IRT: 'تومان' }[building.value?.currency || ''] || building.value?.currency || 'واحد پول ساختمان'));
const allowed = (permission: string) => Boolean(building.value?.abilities[permission]);
const isPayments = computed(() => props.resourceKey === 'payments');
const isPlans = computed(() => props.resourceKey === 'invoice-installments');
const shownInvoices = computed(() => invoiceRows.value.filter(row => (!status.value || row.status === status.value) && [row.invoice_number, row.unit?.unit_number, row.payer?.name].join(' ').toLocaleLowerCase('fa').includes(search.value.trim().toLocaleLowerCase('fa'))));
const shownPayments = computed(() => paymentRows.value.filter(row => (!status.value || row.status === status.value) && [row.payment_number, row.description].join(' ').toLocaleLowerCase('fa').includes(search.value.trim().toLocaleLowerCase('fa'))));
const currentAmount = computed(() => isPayments.value ? shownPayments.value.reduce((sum, row) => sum + row.amount, 0) : shownInvoices.value.reduce((sum, row) => sum + row.total_amount, 0));
const dirtyValue = () => JSON.stringify({ form: form.value, items: items.value, plan: plan.value });
const dirty = computed(() => view.value === 'editor' && dirtyValue() !== snapshot);
const labels = computed(() => editorKind.value === 'invoice' ? ['اطلاعات صورتحساب', 'ردیف‌ها و مبالغ', 'بررسی نهایی'] : [editorKind.value === 'plan' ? 'برنامه اقساط' : editorKind.value === 'payment' ? 'اطلاعات پرداخت' : 'جریمه و بخشودگی', 'بررسی نهایی']);
const editorTitle = computed(() => editorKind.value === 'invoice' ? editingId.value ? 'ویرایش پیش‌نویس' : 'صورتحساب جدید' : editorKind.value === 'plan' ? 'تنظیم برنامه اقساط' : editorKind.value === 'payment' ? 'ثبت پرداخت' : 'جریمه و بخشودگی');
const finalTotal = computed(() => invoiceTotal(items.value, form.value.discount_amount, form.value.penalty_amount));
const planTotal = computed(() => plan.value.reduce((sum, row) => sum + Number(row.amount || 0), 0));
const planDifference = computed(() => Number(selected.value?.total_amount || 0) - planTotal.value);
const activePlan = computed(() => (selected.value?.installments || []).filter(row => row.status !== 'cancelled'));
const request = <T,>(url: string, method = 'GET', body?: unknown) => apiRequest<T>(url, { csrfToken: props.csrfToken, method, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus(); };
const fieldFocus = async (key: string) => { await nextTick(); const input = root.value?.querySelector<HTMLInputElement>('[name="' + CSS.escape(key) + '"]'); (input?.type === 'hidden' ? input.previousElementSibling as HTMLElement : input)?.focus(); };
const href = (key: string, invoice?: Invoice) => {
    const path = props.workspace.navigation.find(item => item.key === key)?.url;
    if (!path) return '';
    const params = new URLSearchParams({ building_id: buildingId.value });
    if (invoice) { params.set('unit_id', String(invoice.unit_id)); params.set('unit_invoice_id', String(invoice.id)); }
    return path + '?' + params;
};
const dateFields: CrudField[] = [
    { name: 'issue_date', label: 'تاریخ صدور', type: 'date', required: true }, { name: 'due_date', label: 'سررسید پرداخت', type: 'date', required: true },
    { name: 'period_start', label: 'شروع بازه صورتحساب', type: 'date' }, { name: 'period_end', label: 'پایان بازه صورتحساب', type: 'date' },
    { name: 'description', label: 'توضیحات', type: 'textarea', maxlength: 5000 },
];
const amountFields = computed<CrudField[]>(() => [{ name: 'discount_amount', label: 'تخفیف (' + currency.value + ')', type: 'number', required: true, min: 0 }, { name: 'penalty_amount', label: 'جریمه اولیه (' + currency.value + ')', type: 'number', required: true, min: 0 }]);
const paymentFields = computed<CrudField[]>(() => [
    { name: 'amount', label: 'مبلغ پرداخت (' + currency.value + ')', type: 'number', min: 1, max: selected.value?.outstanding_amount, required: true },
    { name: 'method', label: 'روش پرداخت', type: 'select', required: true, options: Object.entries(paymentMethods).filter(([key]) => key !== 'online').map(([value, label]) => ({ value, label })) },
    { name: 'description', label: 'توضیحات پرداخت', type: 'textarea', maxlength: 5000 },
]);
const penaltyFields = computed<CrudField[]>(() => [
    { name: 'action', label: 'نوع تغییر', type: 'select', required: true, options: [{ value: 'add', label: 'افزودن جریمه' }, { value: 'waive', label: 'بخشودگی جریمه' }] },
    { name: 'amount', label: 'مبلغ (' + currency.value + ')', type: 'number', min: 1, required: true },
    { name: 'reason', label: 'دلیل تغییر مبلغ', type: 'textarea', maxlength: 2000, required: true },
]);
const translations: Record<string, string> = {
    'Only draft invoices can be edited.': 'فقط پیش‌نویس قابل ویرایش است. اطلاعات را به‌روزرسانی کنید.',
    'Only draft invoices can be issued.': 'فقط پیش‌نویس قابل صدور است.',
    'Invoice total amount must be greater than zero.': 'مبلغ نهایی برای صدور باید بیشتر از صفر باشد.',
    'Payment amount exceeds the currently available invoice outstanding amount.': 'مبلغ پرداخت از مانده قابل پرداخت بیشتر است؛ پرداخت‌های در انتظار نیز ممکن است بخشی از مانده را رزرو کرده باشند. اطلاعات را به‌روزرسانی کنید.',
    'An installment plan cannot be replaced after a payment has been applied.': 'پس از اعمال پرداخت، برنامه اقساط قابل جایگزینی نیست.',
    'The sum of installment amounts must exactly equal the invoice total.': 'جمع اقساط باید دقیقاً برابر مبلغ کل صورتحساب باشد.',
    'Each installment must have a distinct due date.': 'سررسید اقساط باید متفاوت باشد.',
    'Penalty adjustments are only allowed on open issued invoices.': 'تغییر جریمه فقط برای صورتحساب صادرشده و باز ممکن است.',
};
const report = (caught: unknown) => {
    errors.value = caught instanceof ApiError ? caught.errors : {};
    const messages = Object.values(errors.value).flat().map(message => translations[message] || message);
    error.value = messages.join('؛ ') || (caught instanceof Error ? translations[caught.message] || caught.message : 'اطلاعات دریافت نشد. دوباره تلاش کنید.');
    void focus('[data-finance-error]');
};
const updateUrl = () => {
    const url = new URL(window.location.href); url.searchParams.delete('unit_invoice_id'); url.searchParams.delete('charge_period_id');
    buildingId.value ? url.searchParams.set('building_id', buildingId.value) : url.searchParams.delete('building_id');
    unitId.value ? url.searchParams.set('unit_id', unitId.value) : url.searchParams.delete('unit_id');
    window.history.replaceState(window.history.state, '', url);
};
const load = async (target = 1) => {
    const ticket = ++listTicket; invoiceRows.value = []; paymentRows.value = []; error.value = '';
    if (!building.value) { loading.value = false; total.value = 0; lastPage.value = 1; return; }
    loading.value = true;
    const path = isPayments.value ? `/api/v1/buildings/${buildingId.value}/payments` : unitId.value ? `/api/v1/units/${unitId.value}/invoices` : `/api/v1/buildings/${buildingId.value}/invoices`;
    try {
        if (!isPayments.value && !allowed('invoices.view')) throw new Error('برای مشاهده صورتحساب‌ها، دسترسی مشاهده صورتحساب ساختمان لازم است.');
        const result = await request<{ data: (Invoice | Payment)[]; meta?: { current_page: number; last_page: number; total: number } }>(path + `?page=${target}&per_page=20`);
        if (closed || ticket !== listTicket) return;
        if (isPayments.value) paymentRows.value = result.data as Payment[]; else invoiceRows.value = result.data as Invoice[];
        page.value = result.meta?.current_page || 1; lastPage.value = result.meta?.last_page || 1; total.value = result.meta?.total ?? result.data.length;
    } catch (caught) { if (!closed && ticket === listTicket) report(caught); }
    finally { if (ticket === listTicket) loading.value = false; }
};
const changeScope = () => { detailTicket++; selected.value = null; selectedPayment.value = null; view.value = 'list'; unitId.value = ''; status.value = ''; search.value = ''; updateUrl(); void load(); };
const openInvoice = async (id: number) => {
    const ticket = ++detailTicket; detailLoading.value = true; view.value = 'detail'; selected.value = null; selectedPayment.value = null; error.value = ''; success.value = '';
    try {
        const result = await request<{ data: Invoice }>(`/api/v1/invoices/${id}`);
        if (closed || ticket !== detailTicket) return;
        if (String(result.data.building_id) !== buildingId.value) throw new Error('این صورتحساب متعلق به ساختمان انتخاب‌شده نیست.');
        selected.value = result.data; await focus('#finance-detail-title');
    } catch (caught) { if (!closed && ticket === detailTicket) report(caught); }
    finally { if (ticket === detailTicket) detailLoading.value = false; }
};
const backToList = () => { if (busy.value) return; detailTicket++; detailLoading.value = false; view.value = 'list'; error.value = ''; void focus('#finance-list-title'); };
const loadChoices = async (target = 1) => {
    const ticket = ++choiceTicket; choiceLoading.value = true; choiceId.value = ''; choices.value = []; selected.value = null;
    try {
        const result = await request<{ data: Invoice[]; meta?: { last_page: number } }>(`/api/v1/buildings/${buildingId.value}/invoices?page=${target}&per_page=20`);
        if (closed || ticket !== choiceTicket) return;
        choices.value = result.data; choicePage.value = target; choiceLastPage.value = result.meta?.last_page || 1;
    } catch (caught) { if (ticket === choiceTicket) report(caught); }
    finally { if (ticket === choiceTicket) choiceLoading.value = false; }
};
const chooseInvoice = async () => {
    const id = choiceId.value, ticket = ++choiceTicket; selected.value = null; if (!id) return;
    choiceLoading.value = true;
    try { const result = await request<{ data: Invoice }>(`/api/v1/invoices/${id}`); if (!closed && ticket === choiceTicket && String(result.data.building_id) === buildingId.value) selected.value = result.data; }
    catch (caught) { if (ticket === choiceTicket) report(caught); }
    finally { if (ticket === choiceTicket) choiceLoading.value = false; }
};
const openEditor = async (kind: typeof editorKind.value, edit = false) => {
    if (busy.value || !building.value) return;
    if (kind === 'invoice' && (!allowed(edit ? 'invoices.update' : 'invoices.create') || edit && selected.value?.status !== 'draft')) return;
    if (kind === 'plan' && (!selected.value || !canPlan(selected.value) || !allowed('invoices.update'))) return;
    if (kind === 'payment' && (!allowed('payments.create') || !allowed('invoices.view') || selected.value && !payable(selected.value))) return;
    if (kind === 'penalty' && (!selected.value || !payable(selected.value) || !allowed('invoices.adjust'))) return;
    editorKind.value = kind; editingId.value = edit ? selected.value!.id : null; step.value = 0; errors.value = {}; error.value = ''; success.value = ''; discard.value = false; uncertain.value = false;
    if (kind === 'invoice') {
        if (!edit) selected.value = null;
        form.value = edit ? { ...selected.value } : { issue_date: today(), due_date: today(), period_start: '', period_end: '', discount_amount: 0, penalty_amount: 0, description: '', unit_id: unitId.value || '' };
        form.value.unit_id = edit ? String(selected.value!.unit_id) : unitId.value || '';
        items.value = edit ? (selected.value!.items || []).map(item => ({ ...item })) : [{ title: '', quantity: 1, unit_amount: '' }];
    } else if (kind === 'plan') {
        form.value = {}; plan.value = activePlan.value.length >= 2 ? activePlan.value.map(row => ({ ...row })) : [{ amount: '', due_date: '' }, { amount: '', due_date: '' }];
    } else if (kind === 'payment') {
        form.value = { amount: '', method: 'bank_transfer', description: '' }; paymentKey = crypto.randomUUID();
    } else form.value = { action: 'add', amount: '', reason: '' };
    view.value = 'editor'; snapshot = dirtyValue();
    if (kind === 'payment' && !selected.value) await loadChoices();
    await focus('#finance-editor-title');
};
const closeEditor = (force = false) => {
    if (busy.value) return;
    if (!force && dirty.value) { discard.value = true; return; }
    choiceTicket++; choiceLoading.value = false; discard.value = false; error.value = ''; view.value = selected.value ? 'detail' : 'list'; void focus(selected.value ? '#finance-detail-title' : '#finance-list-title');
};
const checkSavedState = () => { closeEditor(true); backToList(); selected.value = null; void load(); };
const validate = () => {
    const next: Record<string, string[]> = {}, add = (key: string, message: string) => next[key] = [message];
    const integer = (value: unknown, min: number) => value !== '' && value !== null && value !== undefined && Number.isSafeInteger(Number(value)) && Number(value) >= min;
    const validDate = (value: unknown) => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(new Date(value + 'T12:00:00').getTime()) && new Date(value + 'T12:00:00').toISOString().slice(0, 10) === value;
    if (editorKind.value === 'invoice') {
        if (!units.value.some(unit => String(unit.id) === String(form.value.unit_id))) add('unit_id', 'یک واحد از همین ساختمان انتخاب کنید.');
        for (const key of ['issue_date', 'due_date']) if (!validDate(form.value[key])) add(key, 'تاریخ معتبر وارد کنید.');
        if (form.value.due_date && form.value.issue_date && String(form.value.due_date) < String(form.value.issue_date)) add('due_date', 'سررسید نباید قبل از تاریخ صدور باشد.');
        for (const key of ['period_start', 'period_end']) if (form.value[key] && !validDate(form.value[key])) add(key, 'تاریخ معتبر وارد کنید.');
        if (form.value.period_start && form.value.period_end && String(form.value.period_end) < String(form.value.period_start)) add('period_end', 'پایان بازه نباید پیش از شروع آن باشد.');
        if (step.value > 0) {
            if (!items.value.length) add('items', 'حداقل یک ردیف اضافه کنید.');
            items.value.forEach((item, index) => { if (!item.title.trim() || item.title.length > 255) add(`items.${index}.title`, 'عنوان ردیف را وارد کنید (حداکثر ۲۵۵ حرف).'); if (!integer(item.quantity, 1)) add(`items.${index}.quantity`, 'تعداد باید عدد صحیح و حداقل یک باشد.'); if (!integer(item.unit_amount, 0)) add(`items.${index}.unit_amount`, 'مبلغ باید عدد صحیح و نامنفی باشد.'); });
            for (const key of ['discount_amount', 'penalty_amount']) if (!integer(form.value[key], 0)) add(key, 'مبلغ صحیح و نامنفی وارد کنید.');
            if (!Number.isSafeInteger(finalTotal.value) || !Number.isSafeInteger(itemTotal(items.value))) add('items', 'مجموع مبالغ از محدوده عددی قابل پشتیبانی بیشتر است.');
        }
    } else if (editorKind.value === 'plan') {
        if (!selected.value || !canPlan(selected.value)) add('installments', 'تقسیط فقط برای صورتحساب صادرشده یا سررسید گذشته، پیش از اعمال پرداخت ممکن است.');
        if (plan.value.length < 2 || plan.value.length > 60) add('installments', 'بین ۲ تا ۶۰ قسط تعریف کنید.');
        if (planDifference.value !== 0) add('installments', 'جمع مبالغ اقساط باید دقیقاً برابر مبلغ کل صورتحساب باشد.');
        plan.value.forEach((item, index) => { if (!integer(item.amount, 1)) add(`installments.${index}.amount`, 'مبلغ هر قسط باید عدد صحیح مثبت باشد.'); if (!validDate(item.due_date) || item.due_date < today()) add(`installments.${index}.due_date`, 'سررسید معتبر، از امروز به بعد انتخاب کنید.'); else if (plan.value.filter(row => row.due_date === item.due_date).length !== 1) add(`installments.${index}.due_date`, 'سررسید اقساط باید متفاوت باشد.'); });
    } else {
        if (!selected.value || !payable(selected.value)) add('invoice', 'یک صورتحساب باز و دارای مانده انتخاب کنید.');
        if (!integer(form.value.amount, 1)) add('amount', 'مبلغ صحیح و بیشتر از صفر وارد کنید.');
        if (editorKind.value === 'payment' && Number(form.value.amount) > Number(selected.value?.outstanding_amount)) add('amount', 'مبلغ از مانده صورتحساب بیشتر است.');
        if (editorKind.value === 'payment' && (!paymentMethods[String(form.value.method)] || form.value.method === 'online')) add('method', 'روش ثبت پرداخت را انتخاب کنید.');
        if (editorKind.value === 'penalty' && !String(form.value.reason || '').trim()) add('reason', 'دلیل تغییر مبلغ الزامی است.');
    }
    errors.value = next;
    if (Object.keys(next).length) { error.value = Object.values(next).flat().join('؛ '); const key = Object.keys(next)[0]; if (editorKind.value === 'invoice') step.value = key.startsWith('items') || ['discount_amount', 'penalty_amount'].includes(key) ? 1 : 0; else step.value = 0; void fieldFocus(key); return false; }
    return true;
};
const save = async () => {
    if (busy.value || choiceLoading.value || uncertain.value || !validate()) return;
    error.value = '';
    if (step.value < labels.value.length - 1) { step.value++; await focus('#finance-step-title'); return; }
    busy.value = true;
    try {
        let savedId = selected.value?.id;
        if (editorKind.value === 'invoice') {
            const body = { ...form.value, items: items.value.map(({ title, quantity, unit_amount, description, charge_item_id, metadata }) => ({ title: title.trim(), quantity: Number(quantity), unit_amount: Number(unit_amount), description, charge_item_id, metadata })), period_start: form.value.period_start || null, period_end: form.value.period_end || null };
            const result = await request<{ data: Invoice }>(editingId.value ? `/api/v1/invoices/${editingId.value}` : `/api/v1/units/${form.value.unit_id}/invoices`, editingId.value ? 'PATCH' : 'POST', body); savedId = result.data.id;
        } else if (editorKind.value === 'plan') await request(`/api/v1/invoices/${savedId}/installments`, 'PUT', { installments: plan.value.map(({ amount, due_date, metadata }) => ({ amount: Number(amount), due_date, metadata })) });
        else if (editorKind.value === 'payment') await request(`/api/v1/invoices/${savedId}/payments`, 'POST', { ...form.value, amount: Number(form.value.amount), idempotency_key: paymentKey });
        else await request(`/api/v1/invoices/${savedId}/penalty-adjustments`, 'POST', { ...form.value, amount: Number(form.value.amount) });
        busy.value = false; view.value = 'list'; snapshot = dirtyValue();
        if (savedId && !isPayments.value) await openInvoice(savedId); else await load(page.value);
        success.value = editorKind.value === 'invoice' ? 'پیش‌نویس صورتحساب ذخیره شد. برای نهایی‌شدن، آن را پس از بررسی صادر کنید.' : editorKind.value === 'payment' ? 'پرداخت ثبت شد؛ اعمال آن روی بدهی پس از تأیید انجام می‌شود.' : 'تغییرات ذخیره شد.';
    } catch (caught) {
        report(caught); step.value = 0;
        if (editorKind.value !== 'payment' && (!(caught instanceof ApiError) || caught.status >= 500)) { uncertain.value = true; error.value += ' نتیجه ثبت مشخص نیست؛ پیش از تکرار، فهرست را به‌روزرسانی کنید.'; }
    } finally { busy.value = false; }
};
const perform = async () => {
    const action = confirmation.value; if (!action || busy.value) return;
    if (!allowed(action === 'verify' ? 'payments.verify' : action === 'issue' ? 'invoices.issue' : action === 'void' ? 'invoices.delete' : 'invoices.update')) return;
    busy.value = true; error.value = ''; let succeeded = false;
    try {
        if (action === 'verify') await request(`/api/v1/payments/${selectedPayment.value!.id}/verify`, 'POST', {});
        else if (action === 'issue') await request(`/api/v1/invoices/${selected.value!.id}/issue`, 'POST', {});
        else if (action === 'void') await request(`/api/v1/invoices/${selected.value!.id}`, 'DELETE');
        else await request(`/api/v1/invoices/${selected.value!.id}/installments`, 'DELETE');
        succeeded = true;
    } catch (caught) { report(caught); }
    finally { busy.value = false; confirmation.value = null; }
    if (succeeded) {
        if (action === 'verify' || action === 'void') { view.value = 'list'; selectedPayment.value = null; await load(page.value); }
        else await openInvoice(selected.value!.id);
        success.value = 'عملیات انجام شد و اطلاعات به‌روزرسانی شد.';
    }
};
const confirmationText = computed(() => confirmation.value === 'issue' ? 'صورتحساب پس از صدور، پیش‌نویس قابل ویرایش نخواهد بود. صدور انجام شود؟' : confirmation.value === 'void' ? 'این پیش‌نویس باطل شود؟' : confirmation.value === 'verify' ? 'تأیید پرداخت موجب ثبت اثر مالی و کاهش بدهی صورتحساب می‌شود. ادامه می‌دهید؟' : 'برنامه اقساط لغو شود؟ مبلغ اصلی صورتحساب باقی می‌ماند.');
const beforeLeave = (event: BeforeUnloadEvent) => { if (dirty.value || busy.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(async () => {
    window.addEventListener('beforeunload', beforeLeave);
    const requested = props.workspace.initial.building_id;
    buildingId.value = props.workspace.buildings.some(item => String(item.id) === requested) ? requested! : !requested && props.workspace.buildings.length === 1 ? String(props.workspace.buildings[0].id) : '';
    unitId.value = units.value.some(item => String(item.id) === props.workspace.initial.unit_id) ? props.workspace.initial.unit_id! : '';
    const id = props.workspace.initial.unit_invoice_id;
    if (id && /^\d+$/.test(id)) {
        try { const result = await request<{ data: Invoice }>(`/api/v1/invoices/${id}`); if (!closed && props.workspace.buildings.some(item => item.id === result.data.building_id) && (!requested || requested === String(result.data.building_id))) { buildingId.value = String(result.data.building_id); selected.value = result.data; view.value = 'detail'; } else throw new Error('صورتحساب در ساختمان انتخاب‌شده قابل دسترسی نیست.'); }
        catch (caught) { if (!closed) report(caught); }
    } else await load();
});
onBeforeUnmount(() => { closed = true; listTicket++; detailTicket++; choiceTicket++; window.removeEventListener('beforeunload', beforeLeave); });
</script>

<template>
    <FinanceFrame :title="title" :resource-key="resourceKey" :workspace="workspace" :building-id="buildingId">
        <div ref="root">
            <div v-if="error" class="ownership-alert ownership-alert--error" role="alert" tabindex="-1" data-finance-error>{{ error }}</div>
            <div v-if="success" class="ownership-alert ownership-alert--success" role="status">{{ success }}</div>
            <section v-if="view === 'list'" class="ownership-card" aria-labelledby="finance-scope-title">
                <div class="ownership-card__heading"><div><h3 id="finance-scope-title">انتخاب ساختمان<template v-if="!isPayments"> و واحد</template></h3><p>اطلاعات فقط برای محدوده انتخاب‌شده نمایش داده می‌شود.</p></div></div>
                <div class="finance-filters"><label class="ownership-field"><span>ساختمان</span><select v-model="buildingId" data-finance-building data-select2="off" @change="changeScope"><option value="">انتخاب ساختمان</option><option v-for="item in workspace.buildings" :key="item.id" :value="String(item.id)">{{ item.title }}</option></select></label><label v-if="!isPayments" class="ownership-field"><span>واحد</span><select v-model="unitId" :disabled="!building" data-finance-unit data-select2="off" @change="updateUrl(); load()"><option value="">همه واحدهای ساختمان</option><option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.label }}</option></select></label></div>
                <p v-if="!workspace.buildings.length" class="finance-empty">ساختمان قابل دسترسی برای این بخش وجود ندارد.</p>
            </section>
            <section v-if="view === 'list' && building" class="ownership-card" aria-labelledby="finance-list-title" :aria-busy="loading">
                <div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-list-title" tabindex="-1">{{ isPlans ? 'انتخاب صورتحساب برای اقساط' : isPayments ? 'فهرست پرداخت‌ها' : 'فهرست صورتحساب‌ها' }}</h3><p>{{ total.toLocaleString('fa-IR') }} مورد ثبت‌شده · {{ currency }}</p></div><div class="finance-actions"><button type="button" class="ownership-button" :disabled="loading" @click="load(page)">به‌روزرسانی</button><button v-if="!isPayments && !isPlans && allowed('invoices.create')" type="button" class="ownership-button ownership-button--primary" data-finance-create @click="openEditor('invoice')">+ صورتحساب جدید</button><button v-if="isPayments && allowed('payments.create') && allowed('invoices.view')" type="button" class="ownership-button ownership-button--primary" data-finance-create @click="selected = null; openEditor('payment')">+ ثبت پرداخت</button></div></div>
                <p v-if="isPlans" class="finance-hint">صورتحساب را باز کنید تا برنامه اقساط آن را ببینید یا تنظیم کنید. تقسیط پیش از اعمال پرداخت و برای صورتحساب صادرشده انجام می‌شود.</p>
                <div class="finance-filters"><label class="ownership-field"><span>جست‌وجو در همین صفحه</span><input v-model="search" type="search" placeholder="شماره سند یا نام پرداخت‌کننده"></label><label class="ownership-field"><span>وضعیت در همین صفحه</span><select v-model="status" data-select2="off"><option value="">همه وضعیت‌ها</option><option v-for="key in (isPayments ? ['pending','processing','paid','failed','cancelled','refunded','partial_refund'] : ['draft','issued','partial','paid','overdue','void','cancelled'])" :key="key" :value="key">{{ invoiceLabels[key] }}</option></select></label></div>
                <div v-if="loading" class="finance-empty" role="status">در حال دریافت اطلاعات…</div>
                <template v-else>
                    <p class="finance-hint">جمع مبالغ {{ isPayments ? shownPayments.length : shownInvoices.length }} سند نمایش‌داده‌شده: {{ money(currentAmount) }} {{ currency }}</p>
                    <div v-if="!(isPayments ? shownPayments.length : shownInvoices.length)" class="finance-empty">{{ search || status ? 'موردی با این جست‌وجو یا وضعیت در این صفحه پیدا نشد.' : 'هنوز سندی برای این محدوده ثبت نشده است.' }}</div>
                    <div v-else class="finance-table-region" role="region" :aria-label="title" tabindex="0"><table class="finance-table"><caption class="finance-sr-only">{{ title }} در محدوده انتخاب‌شده</caption><thead><tr><th scope="col">شماره سند</th><th scope="col">{{ isPayments ? 'روش پرداخت' : 'واحد / پرداخت‌کننده' }}</th><th scope="col">مبلغ ({{ currency }})</th><th scope="col">{{ isPayments ? 'تاریخ پرداخت' : 'مانده / سررسید' }}</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead><tbody>
                        <template v-if="isPayments"><tr v-for="row in shownPayments" :key="row.id"><td><bdi>{{ row.payment_number }}</bdi></td><td>{{ paymentMethods[row.method] || row.method }}</td><td>{{ money(row.amount) }}</td><td>{{ day(row.paid_at) }}</td><td><span class="finance-status" :data-status="row.status">{{ invoiceLabels[row.status] || row.status }}</span></td><td><button type="button" class="ownership-button" data-finance-open @click="selectedPayment = row; selected = null; view = 'detail'">جزئیات پرداخت</button></td></tr></template>
                        <template v-else><tr v-for="row in shownInvoices" :key="row.id"><td><bdi>{{ row.invoice_number }}</bdi></td><td>{{ row.unit?.unit_number ? 'واحد ' + row.unit.unit_number : workspace.units.find(unit => unit.id === row.unit_id)?.label || 'واحد ' + row.unit_id }}<small>{{ row.payer?.name || 'پرداخت‌کننده مشخص نشده' }}</small></td><td>{{ money(row.total_amount) }}</td><td>{{ money(row.outstanding_amount) }}<small>{{ day(row.due_date) }}</small></td><td><span class="finance-status" :data-status="row.status">{{ invoiceLabels[row.status] || row.status }}</span></td><td><button type="button" class="ownership-button" data-finance-open @click="openInvoice(row.id)">{{ isPlans ? 'مشاهده اقساط' : 'مشاهده صورتحساب' }}</button></td></tr></template>
                    </tbody></table></div>
                </template>
                <nav v-if="lastPage > 1" class="ownership-actions finance-pagination" aria-label="صفحه‌بندی اسناد"><button type="button" class="ownership-button" :disabled="loading || page <= 1" @click="load(page - 1)">صفحه قبل</button><span>صفحه {{ money(page) }} از {{ money(lastPage) }}</span><button type="button" class="ownership-button" :disabled="loading || page >= lastPage" @click="load(page + 1)">صفحه بعد</button></nav>
            </section>
            <section v-if="view === 'detail'" class="ownership-card" :aria-busy="detailLoading" aria-labelledby="finance-detail-title">
                <div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-detail-title" tabindex="-1">{{ selectedPayment ? 'جزئیات پرداخت' : isPlans ? 'اقساط صورتحساب' : 'جزئیات صورتحساب' }}</h3><p><bdi>{{ selectedPayment?.payment_number || selected?.invoice_number }}</bdi></p></div><button type="button" class="ownership-button" :disabled="busy" @click="backToList">بازگشت به فهرست</button></div>
                <div v-if="detailLoading" class="finance-empty" role="status">در حال دریافت جزئیات…</div>
                <template v-else-if="selected">
                    <span class="finance-status" :data-status="selected.status">{{ invoiceLabels[selected.status] }}</span>
                    <div class="finance-metrics"><div><span>مبلغ کل ({{ currency }})</span><strong>{{ money(selected.total_amount) }}</strong></div><div><span>پرداخت‌شده</span><strong>{{ money(selected.paid_amount) }}</strong></div><div><span>مانده بدهی</span><strong>{{ money(selected.outstanding_amount) }}</strong></div></div>
                    <dl class="finance-review"><dt>واحد</dt><dd>{{ workspace.units.find(unit => unit.id === selected?.unit_id)?.label || selected.unit?.unit_number || selected.unit_id }}</dd><dt>پرداخت‌کننده</dt><dd>{{ selected.payer?.name || 'مشخص نشده' }}</dd><dt>تاریخ صدور</dt><dd>{{ day(selected.issue_date) }}</dd><dt>سررسید</dt><dd>{{ day(selected.due_date) }}</dd><template v-if="selected.period_start || selected.period_end"><dt>بازه صورتحساب</dt><dd>{{ day(selected.period_start) }} تا {{ day(selected.period_end) }}</dd></template><dt>تخفیف / جریمه</dt><dd>{{ money(selected.discount_amount) }} / {{ money(selected.penalty_amount) }} {{ currency }}</dd><template v-if="selected.description"><dt>توضیحات</dt><dd>{{ selected.description }}</dd></template></dl>
                    <div class="ownership-actions">
                        <button v-if="selected.status === 'draft' && allowed('invoices.update')" type="button" class="ownership-button" data-finance-edit @click="openEditor('invoice', true)">ویرایش پیش‌نویس</button>
                        <button v-if="selected.status === 'draft' && selected.total_amount > 0 && allowed('invoices.issue')" type="button" class="ownership-button ownership-button--primary" data-finance-issue @click="confirmation = 'issue'">صدور صورتحساب</button>
                        <button v-if="selected.status === 'draft' && allowed('invoices.delete')" type="button" class="ownership-button finance-danger" @click="confirmation = 'void'">ابطال پیش‌نویس</button>
                        <button v-if="payable(selected) && allowed('payments.create')" type="button" class="ownership-button ownership-button--primary" data-finance-pay @click="openEditor('payment')">ثبت پرداخت</button>
                        <button v-if="payable(selected) && allowed('invoices.adjust')" type="button" class="ownership-button" @click="openEditor('penalty')">جریمه / بخشودگی</button>
                        <a v-if="!isPlans && workspace.navigation.some(item => item.key === 'invoice-installments')" :href="href('invoice-installments', selected)" class="ownership-button">برنامه اقساط</a>
                        <a v-if="!isPayments && allowed('payments.view')" :href="href('payments')" class="ownership-button">پرداخت‌های ساختمان</a>
                    </div>
                    <section class="finance-detail-section"><h4>ردیف‌های صورتحساب</h4><div class="finance-table-region" role="region" aria-label="ردیف‌های صورتحساب" tabindex="0"><table class="finance-table"><thead><tr><th scope="col">عنوان</th><th scope="col">تعداد</th><th scope="col">مبلغ واحد</th><th scope="col">مبلغ ردیف</th></tr></thead><tbody><tr v-for="(item, index) in selected.items" :key="item.id || index"><td>{{ item.title }}<small v-if="item.description">{{ item.description }}</small></td><td>{{ money(item.quantity) }}</td><td>{{ money(item.unit_amount) }}</td><td>{{ money(Number(item.quantity) * Number(item.unit_amount)) }}</td></tr></tbody></table></div></section>
                    <section class="finance-detail-section"><div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h4>برنامه اقساط</h4><p>جمع اقساط باید برابر مبلغ کل صورتحساب باشد.</p></div><div class="finance-actions"><button v-if="canPlan(selected) && allowed('invoices.update')" type="button" class="ownership-button" data-finance-plan @click="openEditor('plan')">{{ activePlan.length ? 'جایگزینی برنامه اقساط' : 'تعریف اقساط' }}</button><button v-if="activePlan.length && activePlan.every(row => !row.paid_amount) && allowed('invoices.update')" type="button" class="ownership-button finance-danger" @click="confirmation = 'cancel-plan'">لغو برنامه</button></div></div><p v-if="!selected.installments?.length" class="finance-empty">برنامه اقساطی ثبت نشده است.</p><div v-else class="finance-table-region" role="region" aria-label="اقساط" tabindex="0"><table class="finance-table"><thead><tr><th scope="col">قسط</th><th scope="col">سررسید</th><th scope="col">مبلغ</th><th scope="col">پرداخت‌شده</th><th scope="col">وضعیت</th></tr></thead><tbody><tr v-for="row in selected.installments" :key="row.id"><td>{{ money(row.installment_number) }}</td><td>{{ day(row.due_date) }}</td><td>{{ money(row.amount) }}</td><td>{{ money(row.paid_amount) }}</td><td><span class="finance-status" :data-status="row.status">{{ invoiceLabels[row.status || ''] || row.status }}</span></td></tr></tbody></table></div><p v-if="!canPlan(selected)" class="finance-hint">تعریف یا جایگزینی برنامه فقط برای صورتحساب صادرشده یا سررسید گذشته و پیش از اعمال پرداخت ممکن است.</p></section>
                </template>
                <template v-else-if="selectedPayment"><dl class="finance-review"><dt>مبلغ</dt><dd>{{ money(selectedPayment.amount) }} {{ currency }}</dd><dt>روش پرداخت</dt><dd>{{ paymentMethods[selectedPayment.method] }}</dd><dt>وضعیت</dt><dd>{{ invoiceLabels[selectedPayment.status] }}</dd><dt>تاریخ پرداخت</dt><dd>{{ day(selectedPayment.paid_at) }}</dd><template v-if="selectedPayment.description"><dt>توضیحات</dt><dd>{{ selectedPayment.description }}</dd></template></dl><div class="ownership-actions"><button v-if="['pending', 'processing'].includes(selectedPayment.status) && selectedPayment.method !== 'online' && allowed('payments.verify')" type="button" class="ownership-button ownership-button--primary" data-finance-verify @click="confirmation = 'verify'">تأیید پرداخت</button><a v-if="selectedPayment.status === 'paid'" class="ownership-button" :href="'/api/v1/payments/' + selectedPayment.id + '/receipt'" target="_blank" rel="noopener">دریافت رسید PDF</a></div><p v-if="selectedPayment.method === 'online' && selectedPayment.status !== 'paid'" class="finance-hint">پرداخت آنلاین از مسیر درگاه بانکی تأیید می‌شود.</p></template>
            </section>
            <section v-if="view === 'editor'" class="ownership-card finance-editor" aria-labelledby="finance-editor-title" :aria-busy="busy">
                <div class="ownership-card__heading ownership-card__heading--split finance-heading"><div><h3 id="finance-editor-title" tabindex="-1">{{ editorTitle }}</h3><p>{{ building?.title }}<template v-if="selected"> · {{ selected.invoice_number }}</template></p></div><button type="button" class="ownership-button" :disabled="busy" @click="closeEditor()">بازگشت</button></div>
                <nav class="ownership-flow" :style="{ '--finance-steps': labels.length }" aria-label="مراحل ثبت"><span v-for="(label, index) in labels" :key="label" :class="{ 'is-current': step === index, 'is-complete': step > index }" :aria-current="step === index ? 'step' : undefined"><b>{{ index + 1 }}</b>{{ label }}</span></nav>
                <h4 id="finance-step-title" tabindex="-1">{{ labels[step] }}</h4>
                <form id="finance-form" novalidate @submit.prevent="save"><fieldset :disabled="busy || uncertain">
                    <template v-if="editorKind === 'invoice' && step === 0"><label class="ownership-field"><span>واحد *</span><select v-model="form.unit_id" name="unit_id" data-select2="off" :disabled="Boolean(editingId)" :aria-invalid="Boolean(errors.unit_id)"><option value="">انتخاب واحد</option><option v-for="unit in units" :key="unit.id" :value="String(unit.id)">{{ unit.label }}</option></select></label><p v-if="!units.length" class="finance-hint">واحدی برای این ساختمان ثبت نشده است؛ ابتدا ساختار ساختمان را تکمیل کنید.</p><div class="finance-detail-section"><DynamicForm v-model="form" :fields="dateFields" :mode="editingId ? 'edit' : 'create'" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" :group-optional="false" /></div></template>
                    <template v-else-if="editorKind === 'invoice' && step === 1"><p class="finance-hint">مبالغ به {{ currency }} هستند. تعداد و مبلغ هر ردیف را وارد کنید.</p><div v-for="(item, index) in items" :key="index" class="finance-line"><div class="finance-line__heading"><strong>ردیف {{ money(index + 1) }}</strong><button type="button" class="ownership-button finance-danger" :disabled="items.length <= 1" :aria-label="'حذف ردیف ' + (index + 1)" @click="items.splice(index, 1)">حذف ردیف</button></div><div class="finance-line-grid"><label class="ownership-field"><span>عنوان *</span><input v-model="item.title" :name="`items.${index}.title`" maxlength="255" :aria-invalid="Boolean(errors[`items.${index}.title`])"></label><label class="ownership-field"><span>تعداد *</span><input v-model="item.quantity" :name="`items.${index}.quantity`" type="number" min="1" step="1" :aria-invalid="Boolean(errors[`items.${index}.quantity`])"></label><label class="ownership-field"><span>مبلغ واحد ({{ currency }}) *</span><input v-model="item.unit_amount" :name="`items.${index}.unit_amount`" type="number" min="0" step="1" :aria-invalid="Boolean(errors[`items.${index}.unit_amount`])"></label></div><details class="ownership-more finance-detail-section"><summary>توضیح ردیف (اختیاری)</summary><label class="ownership-field"><span class="finance-sr-only">توضیح ردیف {{ index + 1 }}</span><textarea v-model="item.description" maxlength="2000" rows="2"></textarea></label></details><p class="finance-line-total">جمع ردیف: {{ money(Number(item.quantity) * Number(item.unit_amount)) }} {{ currency }}</p></div><button type="button" class="ownership-button" data-finance-add-item @click="items.push({ title: '', quantity: 1, unit_amount: '' })">+ ردیف جدید</button><div class="finance-detail-section"><DynamicForm v-model="form" :fields="amountFields" mode="create" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" /></div><div class="finance-metrics"><div><span>جمع ردیف‌ها</span><strong>{{ money(itemTotal(items)) }}</strong></div><div><span>تخفیف</span><strong>{{ money(form.discount_amount) }}</strong></div><div><span>مبلغ نهایی ({{ currency }})</span><strong>{{ money(finalTotal) }}</strong></div></div></template>
                    <template v-else-if="editorKind === 'plan' && step === 0"><p class="finance-hint">مبلغ کل: {{ money(selected?.total_amount) }} {{ currency }}. حداقل ۲ و حداکثر ۶۰ قسط با سررسید متفاوت تعریف کنید.</p><details class="ownership-more"><summary>ساخت اقساط مساوی</summary><div class="finance-grid"><label class="ownership-field"><span>تعداد اقساط</span><input v-model.number="planCount" type="number" min="2" max="60"></label><label class="ownership-field"><span>سررسید اولین قسط</span><UiDateInput v-model="firstDue" name="first_due" /></label></div><div class="ownership-actions"><button type="button" class="ownership-button" :disabled="!splitInstallments(selected?.total_amount || 0, planCount, firstDue).length" @click="plan = splitInstallments(selected!.total_amount, planCount, firstDue)">ساخت برنامه با فاصله ۳۰ روز</button></div><p class="finance-hint">مبالغ بدون اعشار تقسیم می‌شوند و باقی‌مانده به قسط آخر اضافه می‌شود. پیش از ذخیره، تاریخ‌ها را بررسی کنید.</p></details><div v-for="(item, index) in plan" :key="index" class="finance-line"><div class="finance-line__heading"><strong>قسط {{ money(index + 1) }}</strong><button type="button" class="ownership-button finance-danger" :disabled="plan.length <= 2" :aria-label="'حذف قسط ' + (index + 1)" @click="plan.splice(index, 1)">حذف قسط</button></div><div class="finance-grid"><label class="ownership-field"><span>مبلغ ({{ currency }}) *</span><input v-model="item.amount" :name="`installments.${index}.amount`" type="number" min="1" step="1" :aria-invalid="Boolean(errors[`installments.${index}.amount`])"></label><label class="ownership-field"><span>سررسید *</span><UiDateInput v-model="item.due_date" :name="`installments.${index}.due_date`" :aria-invalid="Boolean(errors[`installments.${index}.due_date`])" /></label></div></div><button type="button" class="ownership-button" :disabled="plan.length >= 60" @click="plan.push({amount:'',due_date:''})">+ قسط جدید</button><div class="finance-metrics"><div><span>جمع اقساط</span><strong>{{ money(planTotal) }}</strong></div><div><span>مبلغ صورتحساب</span><strong>{{ money(selected?.total_amount) }}</strong></div><div><span>{{ planDifference < 0 ? 'مازاد اقساط' : 'مانده تقسیم‌نشده' }}</span><strong>{{ money(Math.abs(planDifference)) }}</strong></div></div></template>
                    <template v-else-if="editorKind === 'payment' && step === 0"><div v-if="!selected" class="finance-line"><label class="ownership-field"><span>صورتحساب دارای مانده *</span><select v-model="choiceId" data-finance-payment-invoice data-select2="off" :disabled="choiceLoading" @change="chooseInvoice"><option value="">انتخاب صورتحساب</option><option v-for="row in choices" :key="row.id" :value="String(row.id)" :disabled="!payable(row)">{{ row.invoice_number }} · {{ row.unit?.unit_number ? 'واحد ' + row.unit.unit_number + ' · ' : '' }}{{ money(row.outstanding_amount) }} {{ currency }} · {{ invoiceLabels[row.status] }}</option></select></label><p v-if="choiceLoading" role="status" class="finance-hint">در حال دریافت صورتحساب…</p><div class="ownership-actions finance-pagination"><button type="button" class="ownership-button" :disabled="choiceLoading || choicePage <= 1" @click="loadChoices(choicePage - 1)">صفحه قبل</button><span>صفحه {{ money(choicePage) }} از {{ money(choiceLastPage) }}</span><button type="button" class="ownership-button" :disabled="choiceLoading || choicePage >= choiceLastPage" @click="loadChoices(choicePage + 1)">صفحه بعد</button></div></div><div v-else class="finance-line"><strong>{{ selected.invoice_number }}</strong><p class="finance-hint">مانده بدهی: {{ money(selected.outstanding_amount) }} {{ currency }}</p></div><DynamicForm v-model="form" :fields="paymentFields" mode="create" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" /><p class="finance-hint">این فرم برای ثبت پرداخت انجام‌شده است. پرداخت آنلاین از مسیر درگاه انجام می‌شود. اثر پرداخت پس از تأیید ثبت خواهد شد.</p></template>
                    <DynamicForm v-else-if="editorKind === 'penalty' && step === 0" v-model="form" :fields="penaltyFields" mode="create" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" />
                    <template v-else><dl class="finance-review"><dt>ساختمان</dt><dd>{{ building?.title }}</dd><template v-if="editorKind === 'invoice'"><dt>واحد</dt><dd>{{ units.find(unit => String(unit.id) === String(form.unit_id))?.label }}</dd><dt>تاریخ صدور / سررسید</dt><dd>{{ day(String(form.issue_date)) }} / {{ day(String(form.due_date)) }}</dd><dt>تعداد ردیف‌ها</dt><dd>{{ money(items.length) }}</dd><dt>مبلغ نهایی</dt><dd>{{ money(finalTotal) }} {{ currency }}</dd></template><template v-else><dt>صورتحساب</dt><dd>{{ selected?.invoice_number }}</dd><dt>{{ editorKind === 'plan' ? 'جمع اقساط' : 'مبلغ' }}</dt><dd>{{ money(editorKind === 'plan' ? planTotal : form.amount) }} {{ currency }}</dd><template v-if="editorKind === 'plan'"><dt>تعداد اقساط</dt><dd>{{ money(plan.length) }}</dd><dt>سررسیدها</dt><dd>{{ plan.map(row => day(row.due_date) + ' · ' + money(row.amount)).join('\n') }}</dd></template><template v-if="editorKind === 'payment'"><dt>روش پرداخت</dt><dd>{{ paymentMethods[String(form.method)] }}</dd></template><template v-if="editorKind === 'penalty'"><dt>نوع تغییر</dt><dd>{{ form.action === 'add' ? 'افزودن جریمه' : 'بخشودگی جریمه' }}</dd><dt>دلیل</dt><dd>{{ form.reason }}</dd></template></template></dl><p class="finance-hint">{{ editorKind === 'invoice' ? 'صورتحساب به‌صورت پیش‌نویس ذخیره می‌شود؛ صدور، مرحله‌ای جداگانه است.' : editorKind === 'plan' ? 'برنامه جدید جایگزین برنامه قبلی می‌شود.' : editorKind === 'payment' ? 'ثبت پرداخت به‌معنی تأیید و اعمال آن روی بدهی نیست.' : 'تغییر مبلغ همراه با دلیل در سوابق مالی ثبت می‌شود.' }}</p></template>
                </fieldset><div v-if="discard" class="finance-line" role="alert"><p>تغییرات ذخیره نشده‌اند. از فرم خارج می‌شوید؟</p><div class="finance-actions"><button type="button" class="ownership-button" @click="discard = false">ادامه ویرایش</button><button type="button" class="ownership-button finance-danger" @click="closeEditor(true)">خروج بدون ذخیره</button></div></div><div class="ownership-actions finance-pagination"><button v-if="step > 0" type="button" class="ownership-button" :disabled="busy" @click="step--; error = ''; focus('#finance-step-title')">مرحله قبل</button><button v-if="uncertain" type="button" class="ownership-button" @click="checkSavedState">بررسی وضعیت در فهرست</button><button type="submit" class="ownership-button ownership-button--primary" :disabled="busy || choiceLoading || uncertain" data-finance-save>{{ busy ? 'در حال ثبت…' : step === labels.length - 1 ? editorKind === 'invoice' ? 'تأیید و ذخیره پیش‌نویس' : 'تأیید و ثبت' : 'ادامه' }}</button></div></form>
            </section>
            <UiConfirmDialog :open="Boolean(confirmation)" :title="confirmation === 'verify' ? 'تأیید پرداخت' : confirmation === 'issue' ? 'صدور صورتحساب' : confirmation === 'void' ? 'ابطال پیش‌نویس' : 'لغو اقساط'" :message="confirmationText" :busy="busy" confirm-label="تأیید و اجرا" @cancel="!busy && (confirmation = null)" @confirm="perform" />
        </div>
    </FinanceFrame>
</template>
