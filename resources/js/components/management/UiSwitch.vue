<script setup lang="ts">
defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{
    modelValue: boolean;
    label: string;
    hideLabel?: boolean;
    id?: string;
    name?: string;
    disabled?: boolean;
    onLabel?: string;
    offLabel?: string;
}>(), { disabled: false, onLabel: 'فعال', offLabel: 'غیرفعال' });
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <label class="buildino-switch">
        <input v-bind="$attrs" :id="id" :name="name" type="checkbox" role="switch"
               class="buildino-switch__input" :aria-label="label" :checked="modelValue" :disabled="disabled"
               @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)">
        <span class="buildino-switch__track" aria-hidden="true"></span>
        <span v-if="!hideLabel" class="buildino-switch__label">{{ label }}</span>
        <span class="buildino-switch__state" aria-hidden="true"><span class="buildino-switch__on">{{ onLabel }}</span><span class="buildino-switch__off">{{ offLabel }}</span></span>
    </label>
</template>
