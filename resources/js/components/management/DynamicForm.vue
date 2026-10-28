<script setup lang="ts">
import {
    computed,
    reactive,
    ref,
    watch,
    type PropType,
} from 'vue';
import { apiRequest, rowsFromPayload } from '../../lib/management-api';
import UiSwitch from './UiSwitch.vue';
import UiLocationPicker from './UiLocationPicker.vue';
import { asMapPoint } from '../../lib/location-picker';
import type {
    CrudField,
    CrudOption,
    CrudRecord,
} from '../../types/management';

const props = defineProps({
    fields: {
        type: Array as PropType<CrudField[]>,
        required: true,
    },
    modelValue: {
        type: Object as PropType<CrudRecord>,
        required: true,
    },
    mode: {
        type: String as PropType<'create' | 'edit'>,
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
    errors: {
        type: Object as PropType<Record<string, string[]>>,
        default: () => ({}),
    },
    groupOptional: { type: Boolean, default: true },
});

const emit = defineEmits<{
    'update:modelValue': [value: CrudRecord];
    loading: [value: boolean];
}>();

const lookupOptions = reactive<Record<string, CrudOption[]>>({});
const loadingFields = ref(new Set<string>());
const requestIds = new Map<string, number>();
const lookupCache = new Map<string, Promise<CrudOption[]>>();
const lookupErrors = reactive<Record<string, string[]>>({});
const optionalNames = new Set(['address', 'postal_code', 'sort_order', 'building_number', 'timezone', 'currency', 'floors_count', 'units_count', 'parking_count', 'storage_count', 'construction_year', 'bedrooms']);

const eligibleFields = computed(() => props.fields.filter((field) => {
    if (props.mode === 'edit' && field.create_only) {
        return false;
    }

    return !(props.mode === 'create' && field.edit_only);
}));
const additionalFields = computed(() => props.groupOptional ? eligibleFields.value.filter((field) => optionalNames.has(field.name) && !field.required && !(props.mode === 'create' && field.required_on_create)) : []);
const visibleFields = computed(() => eligibleFields.value.filter((field) => !additionalFields.value.includes(field)));
const locationFields = computed(() => ['latitude', 'longitude'].every(name => visibleFields.value.some(field => field.name === name)));
const inputFields = computed(() => visibleFields.value.filter(field => !locationFields.value || !['latitude', 'longitude'].includes(field.name)));
const locationDisabled = computed(() => props.mode === 'edit' && props.fields.some(field => ['latitude', 'longitude'].includes(field.name) && field.readonly_on_edit));
const additionalInvalid = computed(() => additionalFields.value.some((field) => props.errors[field.name]?.length));

const inputDirection = (field: CrudField): 'ltr' | undefined => {
    const name = field.name.toLowerCase();

    return [
        'email',
        'password',
        'time',
        'date',
        'datetime-local',
        'number',
    ].includes(field.type ?? '')
        || /mobile|phone|email|code|iban|card|url|website|postal/.test(name)
        ? 'ltr'
        : undefined;
};

const inputMode = (
    field: CrudField,
): 'decimal' | 'email' | 'numeric' | 'tel' | 'url' | undefined => {
    const name = field.name.toLowerCase();
    const type = field.type ?? 'text';

    if (type === 'email' || /email/.test(name)) {
        return 'email';
    }

    if (type === 'number') {
        return field.step && String(field.step) !== '1'
            ? 'decimal'
            : 'numeric';
    }

    if (/mobile|phone/.test(name)) {
        return 'tel';
    }

    if (/iban|card|code|postal/.test(name)) {
        return 'numeric';
    }

    if (type === 'url' || /url|website/.test(name)) {
        return 'url';
    }

    return undefined;
};

const autoComplete = (field: CrudField): string | undefined => {
    const name = field.name.toLowerCase();

    if (field.type === 'password') {
        return 'new-password';
    }

    if (/first_name/.test(name)) {
        return 'given-name';
    }

    if (/last_name/.test(name)) {
        return 'family-name';
    }

    if (/email/.test(name)) {
        return 'email';
    }

    if (/mobile|phone/.test(name)) {
        return 'tel';
    }

    if (/postal/.test(name)) {
        return 'postal-code';
    }

    if (/address/.test(name)) {
        return 'street-address';
    }

    if (/website|url/.test(name)) {
        return 'url';
    }

    return undefined;
};

const fieldDomId = (field: CrudField): string =>
    `management-${props.mode}-${field.name.replace(/[^a-zA-Z0-9_-]/g, '-')}`;

const fieldMessageId = (field: CrudField): string =>
    `${fieldDomId(field)}-message`;

const hasFieldError = (field: CrudField): boolean =>
    Boolean(props.errors?.[field.name]?.length || lookupErrors[field.name]?.length);

const fieldValue = (field: CrudField): unknown =>
    props.modelValue[field.name]
        ?? field.default
        ?? (field.type === 'checkbox' ? false : '');

const optionSelected = (
    field: CrudField,
    option: CrudOption,
): boolean => {
    const value = fieldValue(field);

    if (field.type === 'multiselect') {
        const values = Array.isArray(value) ? value : [];

        return values.map(String).includes(
            String(option.value),
        );
    }

    return String(value ?? '')
        === String(option.value);
};

const setFieldValue = (field: CrudField, value: unknown): void => {
    emit('update:modelValue', {
        ...props.modelValue,
        [field.name]: value,
    });

    void reloadDependents(field.name);
};

const optionRows = (payload: unknown): CrudOption[] =>
    rowsFromPayload<Record<string, unknown>>(payload).map((item) => ({
        value: String(item.id ?? ''),
        label: String(item.label ?? item.title ?? item.name ?? item.id ?? ''),
    }));

const lookupUrl = (field: CrudField): string => {
    const url = new URL(
        `${props.lookupBase}/${encodeURIComponent(field.lookup ?? '')}`,
        window.location.origin,
    );

    if (field.depends_on) {
        const dependentValue = props.modelValue[field.depends_on];

        if (dependentValue !== undefined && dependentValue !== null && dependentValue !== '') {
            url.searchParams.set(field.depends_on, String(dependentValue));
        }
    }

    return url.toString();
};

const fetchLookup = (url: string): Promise<CrudOption[]> => {
    if (lookupCache.has(url)) {
        return lookupCache.get(url) as Promise<CrudOption[]>;
    }

    const request = apiRequest<unknown>(url, {
        method: 'GET',
        csrfToken: props.csrfToken,
    }).then(optionRows).catch((error) => {
        lookupCache.delete(url);
        throw error;
    });

    lookupCache.set(url, request);

    return request;
};

const loadLookup = async (field: CrudField): Promise<void> => {
    if (!field.lookup) {
        return;
    }

    const requestId = (requestIds.get(field.name) ?? 0) + 1;
    requestIds.set(field.name, requestId);
    loadingFields.value = new Set(loadingFields.value).add(field.name);
    delete lookupErrors[field.name];
    emit('loading', true);

    try {
        const options = await fetchLookup(lookupUrl(field));

        if (requestIds.get(field.name) === requestId) {
            lookupOptions[field.name] = options;
        }
    } catch {
        if (requestIds.get(field.name) === requestId) {
            lookupOptions[field.name] = [];
            lookupErrors[field.name] = ['گزینه‌ها بارگذاری نشد؛ دوباره تلاش کنید.'];
        }
    } finally {
        if (requestIds.get(field.name) === requestId) {
            const next = new Set(loadingFields.value);
            next.delete(field.name);
            loadingFields.value = next;
            emit('loading', next.size > 0);
        }
    }
};

const reloadDependents = async (fieldName: string): Promise<void> => {
    const dependents = visibleFields.value.filter(
        (field) => field.depends_on === fieldName && field.lookup,
    );

    await Promise.all(dependents.map(async (field) => {
        emit('update:modelValue', {
            ...props.modelValue,
            [field.name]: field.type === 'multiselect' ? [] : '',
        });
        await loadLookup(field);
        await reloadDependents(field.name);
    }));
};

watch(
    () => [props.fields, props.mode] as const,
    async () => {
        const lookupFields = visibleFields.value.filter((field) => field.lookup);
        const tasks = new Map<string, Promise<void>>();

        const loadWithDependency = (field: CrudField): Promise<void> => {
            const existing = tasks.get(field.name);

            if (existing) {
                return existing;
            }

            const dependency = lookupFields.find(
                (candidate) => candidate.name === field.depends_on,
            );
            const task = (dependency
                ? loadWithDependency(dependency)
                : Promise.resolve()
            ).then(() => loadLookup(field));

            tasks.set(field.name, task);
            return task;
        };

        await Promise.all(lookupFields.map(loadWithDependency));
    },
    { immediate: true },
);
</script>

<template>
    <div class="ui-form-grid">
        <component
            :is="field.type === 'checkbox' ? 'div' : 'label'"
            v-for="field in inputFields"
            :key="field.name"
            class="ui-field"
            :class="{
                'ui-field--wide': ['textarea', 'json', 'multiselect'].includes(field.type ?? ''),
                'ui-field--checkbox': field.type === 'checkbox',
                'has-error': errors?.[field.name]?.length,
            }"
        >
            <span class="ui-field__label">
                {{ field.name === 'is_active' ? 'وضعیت' : field.label }}
                <b v-if="field.required || (mode === 'create' && field.required_on_create)">*</b>
            </span>

            <textarea
                v-if="field.type === 'textarea' || field.type === 'json'"
                :id="fieldDomId(field)"
                :name="field.name"
                :aria-invalid="hasFieldError(field)"
                :aria-describedby="(hasFieldError(field) || field.help) ? fieldMessageId(field) : undefined"
                :rows="field.type === 'json' ? 8 : 5"
                :required="field.required || (mode === 'create' && field.required_on_create)"
                :disabled="mode === 'edit' && field.readonly_on_edit"
                :placeholder="field.placeholder"
                :dir="inputDirection(field)"
                :value="String(fieldValue(field) ?? '')"
                @input="setFieldValue(field, ($event.target as HTMLTextAreaElement).value)"
            />

            <select
                v-else-if="field.type === 'select' || field.type === 'multiselect'"
                data-select2="off"
                :id="fieldDomId(field)"
                :name="field.name"
                :aria-invalid="hasFieldError(field)"
                :aria-describedby="(hasFieldError(field) || field.help) ? fieldMessageId(field) : undefined"
                :aria-busy="loadingFields.has(field.name)"
                :multiple="field.type === 'multiselect'"
                :required="field.required || (mode === 'create' && field.required_on_create)"
                :disabled="loadingFields.has(field.name) || (mode === 'edit' && field.readonly_on_edit)"
                :value="fieldValue(field) as string"
                @change="setFieldValue(field, field.type === 'multiselect'
                    ? [...($event.target as HTMLSelectElement).selectedOptions].map((option) => option.value)
                    : ($event.target as HTMLSelectElement).value)"
            >
                <option
                    v-if="field.type !== 'multiselect'"
                    value=""
                >{{ loadingFields.has(field.name) ? 'در حال بارگذاری...' : 'انتخاب کنید' }}</option>
                <option
                    v-for="option in field.options ?? lookupOptions[field.name] ?? []"
                    :key="String(option.value)"
                    :value="option.value as string | number"
                    :selected="optionSelected(field, option)"
                >{{ option.label }}</option>
            </select>

            <UiSwitch
                v-else-if="field.type === 'checkbox'"
                hide-label
                :id="fieldDomId(field)"
                :name="field.name"
                :label="field.name === 'is_active' ? 'وضعیت' : field.label"
                :on-label="field.name === 'is_active' ? 'فعال' : 'بله'"
                :off-label="field.name === 'is_active' ? 'غیرفعال' : 'خیر'"
                :aria-invalid="hasFieldError(field)"
                :aria-describedby="(hasFieldError(field) || field.help) ? fieldMessageId(field) : undefined"
                :model-value="Boolean(fieldValue(field))"
                :disabled="mode === 'edit' && field.readonly_on_edit"
                @update:model-value="setFieldValue(field, $event)"
            />

            <input
                v-else
                :id="fieldDomId(field)"
                :name="field.name"
                :aria-invalid="hasFieldError(field)"
                :aria-describedby="(hasFieldError(field) || field.help) ? fieldMessageId(field) : undefined"
                :type="['date', 'datetime-local', 'time'].includes(field.type ?? '') ? field.type : (field.type ?? 'text')"
                :required="field.required || (mode === 'create' && field.required_on_create)"
                :disabled="mode === 'edit' && field.readonly_on_edit"
                :placeholder="field.placeholder"
                :step="field.step"
                :dir="inputDirection(field)"
                :inputmode="inputMode(field)"
                :autocomplete="autoComplete(field)"
                :min="field.min"
                :max="field.max"
                :maxlength="field.maxlength"
                :value="fieldValue(field) as string | number"
                @input="setFieldValue(field, ($event.target as HTMLInputElement).value)"
            >

            <small
                v-if="hasFieldError(field)"
                :id="fieldMessageId(field)"
                class="ui-field__error"
                role="alert"
            >{{ (errors[field.name] ?? lookupErrors[field.name])[0] }}</small>
            <small
                v-else-if="field.help"
                :id="fieldMessageId(field)"
                class="ui-field__help"
            >{{ field.help }}</small>
            <button v-if="lookupErrors[field.name]" type="button" class="crud-button crud-button--soft" @click="loadLookup(field)">تلاش مجدد</button>
        </component>
        <UiLocationPicker v-if="locationFields"
            :model-value="asMapPoint(modelValue.latitude, modelValue.longitude)"
            :disabled="locationDisabled"
            :error="[...(errors.latitude ?? []), ...(errors.longitude ?? [])].join(' ')"
            @update:model-value="emit('update:modelValue', { ...modelValue, latitude: $event?.latitude ?? null, longitude: $event?.longitude ?? null })"
        />
        <details v-if="additionalFields.length" class="core-form-details" :open="additionalInvalid">
            <summary>اطلاعات تکمیلی (اختیاری)</summary>
            <DynamicForm
                :fields="additionalFields"
                :group-optional="false"
                :model-value="modelValue"
                :mode="mode"
                :lookup-base="lookupBase"
                :csrf-token="csrfToken"
                :errors="errors"
                @update:model-value="emit('update:modelValue', $event)"
                @loading="emit('loading', $event)"
            />
        </details>
    </div>
</template>
