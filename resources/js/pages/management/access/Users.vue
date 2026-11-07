<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import { ApiError, apiRequest } from '../../../lib/management-api';
import { userIdentityFields, userIdentityValues, validateUserIdentity } from '../../../lib/user-identity';
import type { CrudField, CrudRecord, CrudResource } from '../../../types/management';
import '../../../../css/finance-workspace.css';
import '../../../../css/user-workspace.css';

interface UserRow extends CrudRecord {
    id: number; first_name: string; last_name: string; full_name?: string; mobile: string; email?: string | null;
    national_code?: string | null; is_active: boolean; is_blocked: boolean; mobile_verified_at?: string | null; email_verified_at?: string | null;
    can_update: boolean; can_delete: boolean; roles?: string[];
    user_role_assignments?: { is_active: boolean; starts_at?: string | null; ends_at?: string | null; role?: { display_name?: string; name?: string } }[];
}
const props = defineProps<{ resource: CrudResource; csrfToken: string; workspace: { userId: number; canCreate: boolean; canAssign: boolean; canAccessFlow: boolean; navigation: { title: string; url: string }[] } }>();
const root = ref<HTMLElement | null>(null), rows = ref<UserRow[]>([]), loading = ref(false), busy = ref(false);
const editing = ref(false), selected = ref<UserRow | null>(null), step = ref(0), form = ref<CrudRecord>({});
const errors = ref<Record<string, string[]>>({}), error = ref(''), notice = ref(''), uncertain = ref(false);
const search = ref(''), status = ref(''), page = ref(1), lastPage = ref(1), total = ref(0);
const discard = ref(false), deleting = ref<UserRow | null>(null), created = ref<UserRow | null>(null);
const accessFlow = ref(false);
const ownershipUnit = ref('');
const ownershipReturnUrl = (ownerId?: number) => {
    const query = new URLSearchParams({ unit_id: ownershipUnit.value, resume: 'ownership' });
    if (ownerId) query.set('owner_id', String(ownerId));
    return '/management/operations/ownerships?' + query;
};
const stages = ['مشخصات و وضعیت حساب', 'بررسی نهایی'];
let version = 0, disposed = false, snapshot = '';
let original: CrudRecord = {};
const mode = computed(() => selected.value ? 'edit' as const : 'create' as const);
const name = (row: UserRow) => row.full_name || [row.first_name, row.last_name].filter(Boolean).join(' ') || 'کاربر';
const number = (value: number) => value.toLocaleString('fa-IR');
const roleNames = (row: UserRow) => row.roles || [...new Set((row.user_role_assignments || []).filter(item => item.is_active && (!item.starts_at || new Date(item.starts_at).getTime() <= Date.now()) && (!item.ends_at || new Date(item.ends_at).getTime() >= Date.now())).map(item => item.role?.display_name || item.role?.name).filter(Boolean))];
const stateLabel = (row: UserRow) => row.is_blocked ? 'مسدود' : row.is_active ? 'فعال' : 'غیرفعال';
const roleUrl = (id: number) => '/management/operations/role-assignments?flow=P05&user_id=' + encodeURIComponent(id);
const clearCreateQuery = () => {
    const url = new URL(location.href);
    if (url.searchParams.get('create') !== '1') return;
    url.searchParams.delete('create'); window.history.replaceState(window.history.state, '', url);
};
const fields = computed<CrudField[]>(() => {
    const order = ['is_active', 'is_blocked', 'verify_mobile', 'verify_email'];
    const accountFields = (props.resource.fields || []).filter(field => order.includes(field.name) && !(accessFlow.value && mode.value === 'create' && field.name === 'is_active'))
        .sort((a, b) => order.indexOf(a.name) - order.indexOf(b.name))
        .map(field => ({ ...field, ...(field.name === 'verify_mobile' ? { label: 'موبایل تأییدشده' } : field.name === 'verify_email' ? { label: 'ایمیل تأییدشده' } : {}) }));
    return [...userIdentityFields(props.resource.fields || []), ...accountFields];
});
const focus = async (selector: string) => { await nextTick(); root.value?.querySelector<HTMLElement>(selector)?.focus(); };
const request = <T,>(path: string, method = 'GET', body?: unknown) => apiRequest<T>(path, { csrfToken: props.csrfToken, method, ...(body === undefined ? {} : { body: JSON.stringify(body) }) });
const report = (caught: unknown, mutation = false) => {
    errors.value = caught instanceof ApiError ? caught.errors : {};
    uncertain.value = mutation && (!(caught instanceof ApiError) || caught.status >= 500);
    error.value = uncertain.value ? 'نتیجه ثبت مشخص نیست. پیش از ارسال دوباره، فهرست کاربران را بررسی کنید.'
        : caught instanceof ApiError && caught.status === 403 ? 'اجازه انجام این عملیات را در محدوده این کاربر ندارید.'
        : caught instanceof ApiError && caught.status >= 500 ? 'اطلاعات دریافت نشد؛ دوباره تلاش کنید.'
        : Object.values(errors.value).flat().join('؛ ') || (caught instanceof Error ? caught.message : 'عملیات انجام نشد؛ دوباره تلاش کنید.');
    void focus('[data-users-error]');
};
const load = async (target = 1) => {
    const ticket = ++version; loading.value = true; error.value = ''; rows.value = [];
    try {
        const query = new URLSearchParams({ page: String(target), per_page: '20', ...(search.value.trim() ? { search: search.value.trim().replace(/[۰-۹٠-٩]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))) } : {}), ...(status.value ? { status: status.value } : {}) });
        const result = await request<{ data: UserRow[]; current_page: number; last_page: number; total: number }>('/management/data/users?' + query);
        if (disposed || ticket !== version) return;
        if (!Array.isArray(result.data)) throw new Error('پاسخ فهرست کاربران معتبر نیست.');
        rows.value = result.data; page.value = result.current_page; lastPage.value = result.last_page; total.value = result.total; uncertain.value = false;
    } catch (caught) { if (!disposed && ticket === version) report(caught); }
    finally { if (ticket === version) loading.value = false; }
};
const begin = async (row: UserRow | null = null) => {
    if (busy.value || (row ? !row.can_update : !props.workspace.canCreate)) return;
    version++; loading.value = false; selected.value = row; created.value = null; errors.value = {}; error.value = ''; notice.value = ''; uncertain.value = false; step.value = 0;
    form.value = { first_name: row?.first_name || '', last_name: row?.last_name || '', mobile: row?.mobile || '', email: row?.email || '', national_code: row?.national_code || '',
        is_active: row ? row.is_active : !accessFlow.value, is_blocked: row?.is_blocked ?? false, verify_mobile: row ? Boolean(row.mobile_verified_at) : true, verify_email: Boolean(row?.email_verified_at) };
    original = { ...form.value }; snapshot = JSON.stringify(form.value); editing.value = true; await focus('#users-step-title');
};
const close = async (force = false) => {
    if (busy.value) return;
    if (!force && JSON.stringify(form.value) !== snapshot) { discard.value = true; return; }
    editing.value = false; discard.value = false; error.value = ''; errors.value = {}; clearCreateQuery(); await load(page.value); await focus('#users-list-title');
};
const validate = () => {
    const result = validateUserIdentity(form.value);
    if (form.value.verify_email && !String(form.value.email || '').trim()) result.verify_email = ['برای تأیید ایمیل، ابتدا نشانی ایمیل را وارد کنید.'];
    errors.value = result; return Object.keys(result).length === 0;
};
const navigate = async (index: number) => { if (!busy.value && index < step.value) { step.value = index; errors.value = {}; error.value = ''; await focus('#users-step-title'); } };
const submit = async () => {
    if (busy.value || uncertain.value) return;
    error.value = '';
    if (!validate()) { step.value = 0; await focus('[aria-invalid="true"]'); return; }
    if (step.value === 0) { step.value++; await focus('#users-step-title'); return; }
    const current = selected.value;
    if (current ? !current.can_update : !props.workspace.canCreate) return;
    const values = { ...form.value, ...userIdentityValues(form.value) };
    const body = current ? Object.fromEntries(Object.entries(values).filter(([key, value]) => String(value ?? '') !== String(original[key] ?? ''))) : values;
    busy.value = true;
    try {
        const result = await request<{ data: UserRow }>('/management/data/users' + (current ? '/' + current.id : ''), current ? 'PATCH' : 'POST', body);
        if (!result.data?.id) throw new Error('نتیجه ثبت دریافت نشد.');
        created.value = current ? null : result.data; notice.value = current ? 'اطلاعات کاربر ذخیره شد.' : 'کاربر ساخته شد.';
        clearCreateQuery();
        editing.value = false; search.value = ''; status.value = ''; await load(1); await focus('[data-users-success]');
    } catch (caught) {
        report(caught, true);
        if (Object.keys(errors.value).length) { step.value = 0; await focus('[aria-invalid="true"]'); }
    } finally { busy.value = false; if (error.value) await focus(Object.keys(errors.value).length ? '[aria-invalid="true"]' : '[data-users-error]'); }
};
const remove = async () => {
    const target = deleting.value; if (busy.value || !target?.can_delete || target.id === props.workspace.userId) return;
    busy.value = true;
    try { await request('/management/data/users/' + target.id, 'DELETE'); deleting.value = null; notice.value = 'کاربر حذف شد.'; created.value = null; await load(rows.value.length === 1 ? Math.max(1, page.value - 1) : page.value); }
    catch (caught) { deleting.value = null; report(caught, true); }
    finally { busy.value = false; }
};
const checkList = async () => { editing.value = false; clearCreateQuery(); search.value = ''; status.value = ''; await load(1); await focus('#users-list-title'); };
const unload = (event: BeforeUnloadEvent) => { if (busy.value || editing.value && JSON.stringify(form.value) !== snapshot) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => {
    const query = new URLSearchParams(location.search);
    accessFlow.value = props.workspace.canAccessFlow && query.get('flow') === 'P05';
    const unit = query.get('unit_id') || '';
    ownershipUnit.value = query.get('from') === 'ownership' && /^[1-9]\d*$/.test(unit) ? unit : '';
    if (query.get('create') === '1' && props.workspace.canCreate) void begin(); else void load();
    window.addEventListener('beforeunload', unload);
});
onBeforeUnmount(() => { disposed = true; version++; window.removeEventListener('beforeunload', unload); });
</script>

<template>
    <Head title="مدیریت کاربران" />
    <div ref="root" class="ownership-page finance-page users-page" data-users-page>
        <nav class="finance-breadcrumb" aria-label="مسیر صفحه"><a href="/management">داشبورد</a><span>/</span><a href="/management/operations">مرکز عملیات</a><span>/</span><strong>کاربران</strong></nav>
        <header class="ownership-hero"><div class="ownership-hero__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 3a4 4 0 0 1 0 8M19 14a6 6 0 0 1 3 5v2"/></svg></div><div><span class="eyebrow">کاربران و دسترسی‌ها</span><h2>مدیریت کاربران</h2><p>اطلاعات و وضعیت حساب کاربران در محدوده مدیریت شما.</p></div></header>
        <section v-if="ownershipUnit" class="ownership-card" aria-label="ادامه ثبت مالکیت" data-ownership-return>
            <div class="ownership-card__heading ownership-card__heading--split"><div><h3>ادامه ثبت مالکیت</h3><p>پس از افزودن یا ویرایش کاربر، به فرم مالکیت همان واحد برگردید.</p></div><a :href="ownershipReturnUrl(created?.id)" class="ownership-button ownership-button--primary" :aria-disabled="busy || undefined" @click="busy && $event.preventDefault()">{{ created ? 'ادامه مالکیت با کاربر جدید' : 'بازگشت به ثبت مالکیت' }}</a></div>
        </section>
        <nav v-if="workspace.navigation.length && !editing" class="finance-actions users-navigation" aria-label="صفحات مرتبط"><a v-for="item in workspace.navigation" :key="item.url" :href="item.url" class="ownership-button">{{ item.title }}</a></nav>
        <section v-if="notice" class="ownership-card users-success" role="status" tabindex="-1" data-users-success><strong>{{ notice }}</strong><template v-if="created"><p>{{ name(created) }} — <bdi>{{ created.mobile }}</bdi></p><p>رمز اولیه: <bdi>123456789</bdi>؛ کاربر در اولین ورود باید رمز خود را تغییر دهد.</p><p v-if="!created.is_active">حساب غیرفعال است.</p><a v-if="workspace.canAssign" :href="roleUrl(created.id)" class="ownership-button ownership-button--primary">تخصیص نقش و محدوده</a></template></section>
        <div v-if="error" class="users-error" role="alert" tabindex="-1" data-users-error><p>{{ error }}</p><button v-if="uncertain" class="ownership-button" type="button" :disabled="busy" @click="checkList">بررسی فهرست کاربران</button></div>
        <section v-if="!editing" class="ownership-card" :aria-busy="loading">
            <div class="ownership-card__heading ownership-card__heading--split"><div><h3 id="users-list-title" tabindex="-1">فهرست کاربران</h3><p>{{ number(total) }} کاربر مطابق فیلترها</p></div><button v-if="workspace.canCreate" type="button" class="ownership-button ownership-button--primary" :disabled="busy" data-users-add @click="begin()">+ افزودن کاربر</button></div>
            <form class="users-filters" role="search" @submit.prevent="load(1)"><label class="ownership-field"><span>جست‌وجو</span><input v-model="search" type="search" placeholder="نام، نام خانوادگی، موبایل، ایمیل یا کد ملی" maxlength="255"></label><label class="ownership-field"><span>وضعیت حساب</span><select v-model="status" data-select2="off"><option value="">همه وضعیت‌ها</option><option value="active">فعال</option><option value="inactive">غیرفعال</option><option value="blocked">مسدود</option></select></label><button class="ownership-button" :disabled="loading">اعمال فیلتر</button><button v-if="search || status" type="button" class="ownership-button" :disabled="loading" @click="search = ''; status = ''; load(1)">پاک‌کردن</button></form>
            <p v-if="loading" class="ownership-empty" role="status">در حال دریافت کاربران…</p>
            <div v-else-if="error" class="ownership-empty"><button type="button" class="ownership-button" @click="load(page)">تلاش مجدد</button></div>
            <div v-else-if="!rows.length" class="ownership-empty"><strong>{{ search || status ? 'کاربری با این مشخصات پیدا نشد.' : 'هنوز کاربری در محدوده شما ثبت نشده است.' }}</strong></div>
            <div v-else class="users-list"><article v-for="row in rows" :key="row.id" class="users-item">
                <div class="users-item__heading"><div class="users-avatar" aria-hidden="true">{{ row.first_name?.slice(0, 1) || 'ک' }}</div><div><h4>{{ name(row) }} <small v-if="row.id === workspace.userId">(شما)</small></h4><span class="finance-hint">شناسه {{ number(row.id) }}</span></div><span class="finance-status" :data-status="row.is_blocked ? 'rejected' : row.is_active ? 'approved' : 'pending'">{{ stateLabel(row) }}</span></div>
                <dl class="users-info"><div><dt>موبایل</dt><dd><bdi>{{ row.mobile || '—' }}</bdi></dd></div><div><dt>ایمیل</dt><dd><bdi>{{ row.email || '—' }}</bdi></dd></div><div><dt>نقش‌ها</dt><dd>{{ roleNames(row).join('، ') || 'نقشی تخصیص داده نشده' }}</dd></div></dl>
                <footer v-if="row.can_update || row.can_delete" class="finance-actions"><button v-if="row.can_update" type="button" class="ownership-button" data-users-edit @click="begin(row)">ویرایش کاربر</button><button v-if="row.can_delete && row.id !== workspace.userId" type="button" class="ownership-button finance-danger" :disabled="busy" @click="deleting = row">حذف کاربر</button></footer>
            </article></div>
            <nav v-if="lastPage > 1" class="finance-actions finance-pagination users-pagination" aria-label="صفحه‌بندی"><button type="button" class="ownership-button" :disabled="loading || page <= 1" @click="load(page - 1)">صفحه قبل</button><span>صفحه {{ number(page) }} از {{ number(lastPage) }}</span><button type="button" class="ownership-button" :disabled="loading || page >= lastPage" @click="load(page + 1)">صفحه بعد</button></nav>
        </section>
        <section v-else class="ownership-card finance-editor" :aria-busy="busy">
            <div class="ownership-card__heading ownership-card__heading--split users-editor-heading"><h3>{{ selected ? 'ویرایش کاربر' : 'افزودن کاربر' }}</h3><button type="button" class="ownership-button" :disabled="busy" @click="close()">بازگشت به فهرست</button></div>
            <ol class="ownership-flow" :style="{ '--finance-steps': stages.length }" aria-label="مراحل فرم"><li v-for="(label, index) in stages" :key="label" :aria-current="step === index ? 'step' : undefined" :class="{ 'is-complete': index < step }"><button type="button" :disabled="index >= step || busy" @click="navigate(index)">{{ number(index + 1) }}. {{ label }}</button></li></ol>
            <form id="users-form" :data-users-step="step" novalidate @submit.prevent="submit"><fieldset :disabled="busy || uncertain"><legend id="users-step-title" tabindex="-1">{{ stages[step] }}</legend>
                <p v-if="accessFlow && !selected" class="finance-hint">کاربر تا تکمیل نقش و محدوده غیرفعال می‌ماند.</p>
                <DynamicForm v-if="step < 1" v-model="form" :fields="fields" :mode="mode" :errors="errors" lookup-base="/management/lookups" :csrf-token="csrfToken" :group-optional="false" />
                <template v-else><dl class="finance-review"><dt>نام و نام خانوادگی</dt><dd>{{ form.first_name }} {{ form.last_name }}</dd><dt>موبایل</dt><dd><bdi>{{ form.mobile }}</bdi></dd><dt>ایمیل</dt><dd><bdi>{{ form.email || '—' }}</bdi></dd><dt>کد ملی</dt><dd><bdi>{{ form.national_code || '—' }}</bdi></dd><dt>وضعیت حساب</dt><dd>{{ form.is_blocked ? 'مسدود' : form.is_active ? 'فعال' : 'غیرفعال' }}</dd><dt>تأیید موبایل / ایمیل</dt><dd>{{ form.verify_mobile ? 'موبایل تأییدشده' : 'موبایل تأییدنشده' }} / {{ form.verify_email ? 'ایمیل تأییدشده' : 'ایمیل تأییدنشده' }}</dd></dl><p v-if="!selected" class="finance-hint">رمز اولیه خودکار تعیین می‌شود؛ تغییر آن در اولین ورود الزامی است. تخصیص نقش پس از ساخت کاربر انجام می‌شود.</p></template>
            </fieldset><div class="finance-actions users-form-actions"><button v-if="step" type="button" class="ownership-button" :disabled="busy || uncertain" @click="navigate(step - 1)">مرحله قبل</button><button class="ownership-button ownership-button--primary" :disabled="busy || uncertain" data-users-next>{{ busy ? 'در حال ثبت…' : step === 1 ? 'ثبت اطلاعات' : 'ادامه' }}</button></div></form>
        </section>
        <UiConfirmDialog :open="discard" title="خروج از فرم" message="تغییرات این فرم ذخیره نشده‌اند. از فرم خارج می‌شوید؟" confirm-label="خروج از فرم" @confirm="close(true)" @cancel="discard = false" />
        <UiConfirmDialog :open="Boolean(deleting)" title="حذف کاربر" :message="deleting ? 'کاربر «' + name(deleting) + '» حذف شود؟' : ''" confirm-label="حذف کاربر" :busy="busy" @confirm="remove" @cancel="deleting = null" />
    </div>
</template>
