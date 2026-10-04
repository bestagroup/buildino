<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    computed,
    onMounted,
    ref,
    watch,
    type PropType,
} from 'vue';
import DynamicForm from '../../../components/management/DynamicForm.vue';
import UiConfirmDialog from '../../../components/management/UiConfirmDialog.vue';
import UiDrawer from '../../../components/management/UiDrawer.vue';
import { ApiError, apiRequest, rowsFromPayload } from '../../../lib/management-api';
import type {
    CrudField,
    CrudRecord,
    CrudResource,
    ResourceLink,
} from '../../../types/management';

const props = defineProps({
    resourceKey: {
        type: String,
        required: true,
    },
    resource: {
        type: Object as PropType<CrudResource>,
        required: true,
    },
    groupTitle: {
        type: String,
        required: true,
    },
    siblingResources: {
        type: Array as PropType<ResourceLink[]>,
        required: true,
    },
    operationsUrl: {
        type: String,
        required: true,
    },
    lookupBase: {
        type: String,
        required: true,
    },
    csrfToken: {
        type: String,
        required: true,
    },
});

const rows = ref<CrudRecord[]>([]);
const loading = ref(true);
const pageError = ref('');
const search = ref('');
const currentPage = ref(1);
const pageSize = 25;

const drawerOpen = ref(false);
const drawerMode = ref<'create' | 'edit'>('create');
const selectedRow = ref<CrudRecord | null>(null);
const form = ref<CrudRecord>({});
const formErrors = ref<Record<string, string[]>>({});
const formError = ref('');
const lookupsLoading = ref(false);
const saving = ref(false);

const deleteTarget = ref<CrudRecord | null>(null);
const deleting = ref(false);
const toast = ref<{ message: string; tone: 'success' | 'danger' } | null>(null);
let toastTimer: number | undefined;

const filteredRows = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('fa');

    if (!term) {
        return rows.value;
    }

    return rows.value.filter((row) =>
        JSON.stringify(row).toLocaleLowerCase('fa').includes(term),
    );
});

const pageCount = computed(() => Math.max(
    1,
    Math.ceil(filteredRows.value.length / pageSize),
));

const visibleRows = computed(() => {
    const safePage = Math.min(currentPage.value, pageCount.value);
    const start = (safePage - 1) * pageSize;

    return filteredRows.value.slice(start, start + pageSize);
});

const getByPath = (record: CrudRecord, path: string): unknown =>
    path.split('.').reduce<unknown>((value, key) => {
        if (!value || typeof value !== 'object') {
            return null;
        }

        return (value as Record<string, unknown>)[key];
    }, record);

const displayValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'بله' : 'خیر';
    }

    if (Array.isArray(value)) {
        return value.length ? value.map(displayValue).join('، ') : '—';
    }

    if (typeof value === 'object') {
        const data = value as Record<string, unknown>;
        return String(data.title ?? data.name ?? data.label ?? data.id ?? '—');
    }

    return String(value);
};

const interpolate = (template: string, record?: CrudRecord | null): string =>
    template.replace(/\{([^}]+)}/g, (_, name: string) => {
        const value = name === 'id' ? record?.id : getByPath(record ?? {}, name);

        if (value === undefined || value === null || value === '') {
            throw new Error(`مقدار «${name}» برای این عملیات موجود نیست.`);
        }

        return encodeURIComponent(String(value));
    });

const notify = (message: string, tone: 'success' | 'danger' = 'success'): void => {
    toast.value = { message, tone };
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toast.value = null;
    }, 4500);
};

const loadRows = async (): Promise<void> => {
    if (!props.resource.list) {
        loading.value = false;
        return;
    }

    loading.value = true;
    pageError.value = '';

    try {
        const payload = await apiRequest<unknown>(
            props.resource.list.url,
            {
                method: props.resource.list.method ?? 'GET',
                csrfToken: props.csrfToken,
            },
        );

        rows.value = rowsFromPayload<CrudRecord>(payload);
        currentPage.value = 1;
    } catch (error) {
        pageError.value = error instanceof Error
            ? error.message
            : 'خطا در بارگذاری اطلاعات.';
    } finally {
        loading.value = false;
    }
};

const initialFieldValue = (
    field: CrudField,
    record: CrudRecord | null,
): unknown => {
    const value = record ? getByPath(record, field.name) : undefined;

    if (value !== undefined && value !== null) {
        if (field.type === 'json' && typeof value !== 'string') {
            return JSON.stringify(value, null, 2);
        }

        return value;
    }

    if (field.default !== undefined) {
        return field.default;
    }

    return field.type === 'checkbox' ? false : '';
};

const openDrawer = (
    mode: 'create' | 'edit',
    record: CrudRecord | null = null,
): void => {
    drawerMode.value = mode;
    selectedRow.value = record;
    formErrors.value = {};
    formError.value = '';
    form.value = Object.fromEntries(
        (props.resource.fields ?? []).map((field) => [
            field.name,
            initialFieldValue(field, record),
        ]),
    );
    drawerOpen.value = true;
};

const closeDrawer = (): void => {
    if (!saving.value) {
        drawerOpen.value = false;
    }
};

const buildPayload = (): CrudRecord => {
    const payload: CrudRecord = {};

    for (const field of props.resource.fields ?? []) {
        if (
            (drawerMode.value === 'edit' && (field.create_only || field.readonly_on_edit))
            || (drawerMode.value === 'create' && field.edit_only)
        ) {
            continue;
        }

        let value = form.value[field.name];

        if (field.type === 'password' && drawerMode.value === 'edit' && !value) {
            continue;
        }

        if (field.type === 'number') {
            value = value === '' || value === null || value === undefined
                ? null
                : Number(value);
        } else if (field.type === 'multiselect') {
            value = Array.isArray(value)
                ? value.map((item) => {
                    const number = Number(item);
                    return Number.isFinite(number) ? number : item;
                })
                : [];
        } else if (field.type === 'json') {
            const source = String(value ?? '').trim();
            if (!source) {
                continue;
            }
            value = JSON.parse(source) as unknown;
        } else if (
            field.type !== 'checkbox'
            && value === ''
            && !(field.required || (drawerMode.value === 'create' && field.required_on_create))
        ) {
            value = null;
        }

        payload[field.name] = value;
    }

    return payload;
};

const save = async (): Promise<void> => {
    const operation = drawerMode.value === 'edit'
        ? props.resource.update
        : props.resource.create;

    if (!operation) {
        return;
    }

    saving.value = true;
    formErrors.value = {};
    formError.value = '';

    try {
        const endpoint = interpolate(operation.url, selectedRow.value);
        const payload = buildPayload();

        await apiRequest<unknown>(endpoint, {
            method: operation.method ?? (drawerMode.value === 'edit' ? 'PATCH' : 'POST'),
            csrfToken: props.csrfToken,
            body: JSON.stringify(payload),
        });

        drawerOpen.value = false;
        notify(drawerMode.value === 'edit' ? 'تغییرات با موفقیت ذخیره شد.' : 'رکورد جدید با موفقیت ثبت شد.');
        await loadRows();
    } catch (error) {
        if (error instanceof ApiError) {
            formErrors.value = error.errors;
            formError.value = error.message;
        } else {
            formError.value = error instanceof Error
                ? error.message
                : 'ذخیره اطلاعات انجام نشد.';
        }
    } finally {
        saving.value = false;
    }
};

const remove = async (): Promise<void> => {
    if (!props.resource.delete || !deleteTarget.value) {
        return;
    }

    deleting.value = true;

    try {
        await apiRequest<unknown>(
            interpolate(props.resource.delete.url, deleteTarget.value),
            {
                method: props.resource.delete.method ?? 'DELETE',
                csrfToken: props.csrfToken,
            },
        );

        deleteTarget.value = null;
        notify('رکورد با موفقیت حذف شد.');
        await loadRows();
    } catch (error) {
        notify(
            error instanceof Error ? error.message : 'حذف رکورد انجام نشد.',
            'danger',
        );
    } finally {
        deleting.value = false;
    }
};

const openRequestedDrawer = (): void => {
    const url = new URL(window.location.href);
    if (url.searchParams.get('create') === '1' && props.resource.create) {
        openDrawer('create');
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url);
    }
};

watch(
    () => [props.resource.title, props.resource.description] as const,
    ([title, description]) => {
        const heading = document.querySelector<HTMLElement>(
            '.page-heading h1',
        );
        const subtitle = document.querySelector<HTMLElement>(
            '.page-heading p',
        );

        if (heading) {
            heading.textContent = title;
        }

        if (subtitle) {
            subtitle.textContent = description ?? '';
        }
    },
    { immediate: true },
);

watch(
    () => props.resourceKey,
    async (resourceKey, previousResourceKey) => {
        if (!previousResourceKey || resourceKey === previousResourceKey) {
            return;
        }

        drawerOpen.value = false;
        deleteTarget.value = null;
        search.value = '';
        currentPage.value = 1;
        await loadRows();
        openRequestedDrawer();
    },
);

onMounted(() => {
    void loadRows();
    openRequestedDrawer();
});
</script>

<template>
    <Head :title="resource.title" />

    <div class="crud-page ui-crud-page">
        <nav class="crud-breadcrumb" aria-label="مسیر صفحه">
            <a href="/management">داشبورد</a>
            <span>/</span>
            <a :href="operationsUrl">عملیات</a>
            <span>/</span>
            <strong>{{ resource.title }}</strong>
        </nav>

        <section class="crud-header ui-page-header">
            <div class="crud-header__copy">
                <span class="eyebrow">{{ groupTitle }}</span>
                <h2>{{ resource.title }}</h2>
                <p>{{ resource.description }}</p>
                <div v-if="resource.note" class="crud-inline-note">{{ resource.note }}</div>
            </div>

            <div class="crud-header__actions">
                <a :href="operationsUrl" class="crud-button crud-button--soft">همه ماژول‌ها</a>
                <button
                    v-if="resource.create"
                    type="button"
                    class="crud-button crud-button--primary"
                    @click="openDrawer('create')"
                >+ ثبت رکورد جدید</button>
            </div>
        </section>

        <nav
            v-if="siblingResources.length > 1"
            class="crud-sibling-nav"
            aria-label="زیرماژول‌های مرتبط"
        >
            <template v-for="sibling in siblingResources" :key="sibling.key">
                <Link
                    v-if="sibling.inertia"
                    :href="sibling.url"
                    :class="{ 'is-active': sibling.key === resourceKey }"
                    prefetch
                >{{ sibling.title }}</Link>
                <a
                    v-else
                    :href="sibling.url"
                    :class="{ 'is-active': sibling.key === resourceKey }"
                >{{ sibling.title }}</a>
            </template>
        </nav>

        <section class="crud-panel ui-data-panel">
            <div class="crud-toolbar">
                <div class="crud-search">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="جستجو در رکوردها..."\n                        aria-label="جستجو در رکوردهای این بخش"
                        @input="currentPage = 1"
                    >
                </div>
                <div class="crud-toolbar__actions">
                    <span class="ui-record-count">{{ filteredRows.length }} رکورد</span>
                    <button
                        type="button"
                        class="crud-button crud-button--soft"
                        :disabled="loading"
                        @click="loadRows"
                    >{{ loading ? 'در حال بارگذاری...' : 'به‌روزرسانی' }}</button>
                </div>
            </div>

            <div v-if="loading" class="ui-table-skeleton" aria-live="polite">
                <span class="crud-state__loader" />
                <p>در حال بارگذاری اطلاعات...</p>
            </div>

            <div v-else-if="pageError" class="crud-empty crud-empty--danger">
                {{ pageError }}
                <button type="button" class="crud-button crud-button--soft" @click="loadRows">تلاش مجدد</button>
            </div>

            <div v-else-if="!filteredRows.length" class="ui-empty-state">
                <div class="ui-empty-state__icon">⌕</div>
                <strong>{{ search ? 'نتیجه‌ای پیدا نشد' : 'هنوز رکوردی ثبت نشده است' }}</strong>
                <p>{{ search ? 'عبارت جستجو را تغییر دهید.' : 'برای شروع یک رکورد جدید ایجاد کنید.' }}</p>
            </div>

            <div v-else class="crud-table-wrap ui-table-wrap">
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th v-for="column in resource.columns ?? []" :key="column.key">{{ column.label }}</th>
                            <th class="crud-actions-column">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in visibleRows" :key="String(row.id)">
                            <td v-for="column in resource.columns ?? []" :key="column.key">
                                <span
                                    v-if="typeof getByPath(row, column.key) === 'boolean'"
                                    class="ui-status"
                                    :class="getByPath(row, column.key) ? 'is-active' : 'is-inactive'"
                                >{{ displayValue(getByPath(row, column.key)) }}</span>
                                <template v-else>{{ displayValue(getByPath(row, column.key)) }}</template>
                            </td>
                            <td class="crud-actions-column">
                                <div class="crud-row-actions">
                                    <button
                                        v-if="resource.update"
                                        type="button"
                                        class="crud-row-action crud-row-action--edit"
                                        @click="openDrawer('edit', row)"
                                    >ویرایش</button>
                                    <button
                                        v-if="resource.delete"
                                        type="button"
                                        class="crud-row-action crud-row-action--danger"
                                        @click="deleteTarget = row"
                                    >حذف</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pageCount > 1" class="ui-pagination">
                <button
                    type="button"
                    class="crud-button crud-button--soft"
                    :disabled="currentPage <= 1"
                    @click="currentPage--"
                >قبلی</button>
                <span>صفحه {{ currentPage }} از {{ pageCount }}</span>
                <button
                    type="button"
                    class="crud-button crud-button--soft"
                    :disabled="currentPage >= pageCount"
                    @click="currentPage++"
                >بعدی</button>
            </div>
        </section>
    </div>

    <UiDrawer
        :open="drawerOpen"
        :title="drawerMode === 'edit' ? `ویرایش ${resource.title}` : `ثبت ${resource.title}`"
        :eyebrow="drawerMode === 'edit' ? 'ویرایش رکورد' : 'رکورد جدید'"
        :busy="saving || lookupsLoading"
        :close-disabled="saving"
        @close="closeDrawer"
    >
        <form id="inertiaCrudForm" @submit.prevent="save">
            <DynamicForm
                v-model="form"
                :fields="resource.fields ?? []"
                :mode="drawerMode"
                :lookup-base="lookupBase"
                :csrf-token="csrfToken"
                :errors="formErrors"
                @loading="lookupsLoading = $event"
            />
            <div v-if="formError" class="crud-form-error">{{ formError }}</div>
        </form>

        <template #footer>
            <button
                type="button"
                class="crud-button crud-button--soft"
                :disabled="saving"
                @click="closeDrawer"
            >انصراف</button>
            <button
                type="submit"
                form="inertiaCrudForm"
                class="crud-button crud-button--primary"
                :disabled="saving || lookupsLoading"
            >{{ saving ? 'در حال ذخیره...' : (drawerMode === 'edit' ? 'ذخیره تغییرات' : 'ثبت رکورد') }}</button>
        </template>
    </UiDrawer>

    <UiConfirmDialog
        :open="Boolean(deleteTarget)"
        title="حذف رکورد"
        message="این رکورد حذف می‌شود و ممکن است بازیابی آن امکان‌پذیر نباشد. آیا ادامه می‌دهید؟"
        confirm-label="حذف رکورد"
        :busy="deleting"
        @cancel="!deleting && (deleteTarget = null)"
        @confirm="remove"
    />

    <Transition name="ui-toast">
        <div
            v-if="toast"
            class="ui-toast"
            :class="`is-${toast.tone}`"
            role="status"
        >
            <span>{{ toast.message }}</span>
            <button type="button" aria-label="بستن" @click="toast = null">×</button>
        </div>
    </Transition>
</template>
