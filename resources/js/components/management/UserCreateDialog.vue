<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import DynamicForm from './DynamicForm.vue';
import { ApiError, apiRequest } from '../../lib/management-api';
import { userIdentityFields, userIdentityValues, validateUserIdentity } from '../../lib/user-identity';
import type { CrudField, CrudRecord } from '../../types/management';
import '../../../css/management-inertia.css';
import '../../../css/user-create-dialog.css';

const props = defineProps<{ fields: CrudField[]; csrfToken: string }>();
const dialog = ref<HTMLDialogElement | null>(null), busy = ref(false), uncertain = ref(false);
const closing = ref(false);
const form = ref<CrudRecord>({ first_name: '', last_name: '', mobile: '', email: '', national_code: '' });
const errors = ref<Record<string, string[]>>({}), error = ref(''), created = ref<CrudRecord | null>(null);
const context = ref({ unitId: 0, unitLabel: '' });
const fields = computed(() => userIdentityFields(props.fields));
let returnFocus: HTMLElement | null = null;
let previousOverflow = '';
const focus = async (selector: string) => { await nextTick(); dialog.value?.querySelector<HTMLElement>(selector)?.focus(); };
const open = async (event: Event) => {
    const detail = (event as CustomEvent).detail;
    if (!Number.isSafeInteger(detail?.unitId) || detail.unitId < 1 || dialog.value?.open) return;
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    context.value = { unitId: detail.unitId, unitLabel: String(detail.unitLabel || '') };
    if (created.value) { created.value = null; form.value = { first_name: '', last_name: '', mobile: '', email: '', national_code: '' }; }
    if (!uncertain.value) { errors.value = {}; error.value = ''; }
    previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden';
    dialog.value?.showModal(); await focus('input');
};
const close = async () => {
    const element = dialog.value;
    if (busy.value || closing.value || !element?.open) return;
    closing.value = true;
    await nextTick();
    let timeout: ReturnType<typeof setTimeout> | undefined;
    await Promise.race([
        Promise.allSettled(element.getAnimations().map(animation => animation.finished)),
        new Promise<void>(resolve => { timeout = setTimeout(resolve, 200); }),
    ]);
    clearTimeout(timeout);
    if (element.isConnected && element.open) element.close();
    closing.value = false;
};
const closed = () => { document.body.style.overflow = previousOverflow; returnFocus?.focus({ preventScroll: true }); };
const submit = async () => {
    if (busy.value || closing.value || uncertain.value || created.value) return;
    errors.value = validateUserIdentity(form.value); error.value = '';
    if (Object.keys(errors.value).length) { await focus('[aria-invalid="true"]'); return; }
    busy.value = true;
    try {
        const response = await apiRequest<{ data: CrudRecord }>('/management/data/users', {
            method: 'POST', csrfToken: props.csrfToken,
            body: JSON.stringify({ ...userIdentityValues(form.value), is_active: true, is_blocked: false, verify_mobile: true, verify_email: false }),
        });
        if (!response.data?.id) throw new Error('Missing user');
        created.value = response.data;
        window.dispatchEvent(new CustomEvent('buildino:user-created', { detail: { user: response.data, unitId: context.value.unitId } }));
        await focus('[data-user-create-done]');
    } catch (caught) {
        errors.value = caught instanceof ApiError ? caught.errors : {};
        uncertain.value = !(caught instanceof ApiError) || caught.status >= 500;
        error.value = uncertain.value ? 'نتیجه ثبت مشخص نیست؛ برای جلوگیری از ساخت تکراری، ابتدا فهرست مالکان را به‌روزرسانی و کاربر را جست‌وجو کنید.'
            : caught instanceof ApiError && [401, 419].includes(caught.status) ? 'نشست شما پایان یافته است؛ دوباره وارد شوید.'
            : caught instanceof ApiError && caught.status === 403 ? 'اجازه افزودن کاربر در این محدوده را ندارید.'
            : Object.keys(errors.value).length ? 'فیلدهای مشخص‌شده را اصلاح کنید.' : 'ثبت کاربر انجام نشد؛ دوباره تلاش کنید.';
        await focus(Object.keys(errors.value).length ? '[aria-invalid="true"]' : '[data-user-create-error]');
    } finally { busy.value = false; }
};
const unload = (event: BeforeUnloadEvent) => { if (busy.value) { event.preventDefault(); event.returnValue = ''; } };
onMounted(() => {
    window.addEventListener('buildino:user-create', open); window.addEventListener('beforeunload', unload);
    const host = document.querySelector<HTMLElement>('[data-user-create-root]'); if (host) host.dataset.ready = 'true';
    window.dispatchEvent(new CustomEvent('buildino:user-create-ready'));
});
onBeforeUnmount(() => { window.removeEventListener('buildino:user-create', open); window.removeEventListener('beforeunload', unload); if (dialog.value?.open) closed(); });
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" class="user-create-dialog" :class="{ 'is-closing': closing }" aria-labelledby="user-create-title" aria-describedby="user-create-context" @cancel.prevent="close" @close="closed">
            <header class="user-create-dialog__header"><div><h3 id="user-create-title">{{ created ? 'کاربر ساخته شد' : 'افزودن کاربر' }}</h3><p id="user-create-context">{{ context.unitLabel }}</p></div><button type="button" class="ownership-button user-create-dialog__close" :disabled="busy || closing" aria-label="بستن پنجره افزودن کاربر" @click="close">×</button></header>
            <section v-if="created" class="user-create-dialog__success" role="status">
                <strong>{{ created.first_name }} {{ created.last_name }}</strong><p>کاربر به فهرست مالک‌ها اضافه شد. برای اتصال او به واحد، ثبت مالکیت را تکمیل کنید.</p>
                <p>رمز اولیه: <bdi>123456789</bdi>؛ تغییر رمز در اولین ورود الزامی است.</p>
                <button type="button" class="ownership-button ownership-button--primary" data-user-create-done @click="close">ادامه ثبت مالکیت</button>
            </section>
            <form v-else novalidate :aria-busy="busy" @submit.prevent="submit">
                <p class="user-create-dialog__hint">اطلاعات حساب کاربری را وارد کنید؛ مالکیت در مرحله بعد ثبت می‌شود.</p>
                <p v-if="error" role="alert" tabindex="-1" class="ownership-alert ownership-alert--error" data-user-create-error>{{ error }}</p>
                <fieldset :disabled="busy || closing || uncertain"><legend class="visually-hidden">مشخصات کاربر</legend><DynamicForm v-model="form" :fields="fields" mode="create" lookup-base="/management/lookups" :csrf-token="csrfToken" :errors="errors" :group-optional="false" /></fieldset>
                <footer class="user-create-dialog__actions"><button type="button" class="ownership-button" :disabled="busy || closing" @click="close">بازگشت به مالکیت</button><button type="submit" class="ownership-button ownership-button--primary" :disabled="busy || closing || uncertain" data-user-create-save>{{ busy ? 'در حال ثبت…' : 'ثبت کاربر' }}</button></footer>
            </form>
        </dialog>
    </Teleport>
</template>
