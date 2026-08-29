<script setup lang="ts">
import {
    computed,
    reactive,
    ref,
    watch,
    type PropType,
} from 'vue';
import { apiRequest, rowsFromPayload } from '../../lib/management-api';
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
});

const emit = defineEmits<{
    'update:modelValue': [value: CrudRecord];
    loading: [value: boolean];
}>();

const lookupOptions = reactive<Record<string, CrudOption[]>>({});
const loadingFields = ref(new Set<string>());
const requestIds = new Map<string, number>();
const lookupCache = new Map<string, Promise<CrudOption[]>>();

const visibleFields = computed(() => props.fields.filter((field) => {
    if (props.mode === 'edit' && field.create_only) {
        return false;
    }

    return !(props.mode === 'create' && field.edit_only);
}));

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
        || /mobile|phone|email|code|iban|card|url|website/.test(name)
        ? 'ltr'
        : undefined;
};

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
    emit('loading', true);

    try {
        const options = await fetchLookup(lookupUrl(field));

        if (requestIds.get(field.name) === requestId) {
            lookupOptions[field.name] = options;
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
        <label
            v-for="field in visibleFields"
            :key="field.name"
            class="ui-field"
            :class="{
                'ui-field--wide': ['textarea', 'json', 'multiselect'].includes(field.type ?? ''),
                'ui-field--checkbox': field.type === 'checkbox',
                'has-error': errors?.[field.name]?.length,
            }"
        >
            <span class="ui-field__label">
                {{ field.label }}
                <b v-if="field.required || (mode === 'create' && field.required_on_create)">*</b>
            </span>

            <textarea
                v-if="field.type === 'textarea' || field.type === 'json'"
                :name="field.name"
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
                :name="field.name"
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

            <span
                v-else-if="field.type === 'checkbox'"
                class="ui-checkbox"
            >
                <input
                    :name="field.name"
                    type="checkbox"
                    :checked="Boolean(fieldValue(field))"
                    :disabled="mode === 'edit' && field.readonly_on_edit"
                    @change="setFieldValue(field, ($event.target as HTMLInputElement).checked)"
                >
                <span>فعال</span>
            </span>

            <input
                v-else
                :name="field.name"
                :type="['date', 'datetime-local', 'time'].includes(field.type ?? '') ? field.type : (field.type ?? 'text')"
                :required="field.required || (mode === 'create' && field.required_on_create)"
                :disabled="mode === 'edit' && field.readonly_on_edit"
                :placeholder="field.placeholder"
                :step="field.step"
                :dir="inputDirection(field)"
                :autocomplete="field.type === 'password' ? 'new-password' : undefined"
                :value="fieldValue(field) as string | number"
                @input="setFieldValue(field, ($event.target as HTMLInputElement).value)"
            >

            <small
                v-if="errors?.[field.name]?.length"
                class="ui-field__error"
            >{{ errors[field.name][0] }}</small>
            <small
                v-else-if="field.help"
                class="ui-field__help"
            >{{ field.help }}</small>
        </label>
    </div>
</template>
