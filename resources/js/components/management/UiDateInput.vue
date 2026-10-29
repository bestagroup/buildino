<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useAttrs, watch } from 'vue';
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ modelValue?: string | number | null; type?: 'date' | 'datetime-local' | 'time'; name?: string; disabled?: boolean }>(), { type: 'date' });
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const attrs = useAttrs(), input = ref<HTMLInputElement | null>(null), text = ref(''), ready = ref(false), invalid = ref(false);
type Calendar = { serverToJalali: (value: unknown, type: string) => string; jalaliToServer: (value: string, type: string) => string | null };
const calendar = () => (window as unknown as { BuildinoJalaliDatepicker?: Calendar }).BuildinoJalaliDatepicker;
let lastEmitted: string | null = null;
const synchronize = () => { ready.value = Boolean(calendar()); text.value = calendar()?.serverToJalali(props.modelValue, props.type) || String(props.modelValue || ''); };
const update = () => {
    text.value = input.value?.value || '';
    const value = calendar()?.jalaliToServer(text.value, props.type);
    invalid.value = value === null;
    input.value?.setCustomValidity(invalid.value ? 'تاریخ یا ساعت معتبر انتخاب کنید.' : '');
    lastEmitted = value ?? text.value;
    emit('update:modelValue', lastEmitted);
};
watch(() => [props.modelValue, props.type], () => { if (props.modelValue !== lastEmitted) { synchronize(); invalid.value = false; input.value?.setCustomValidity(''); } }, { immediate: true });
onMounted(() => { synchronize(); window.addEventListener('buildino:datepicker-ready', synchronize); input.value?.addEventListener('jdp:change', update); });
onBeforeUnmount(() => { window.removeEventListener('buildino:datepicker-ready', synchronize); input.value?.removeEventListener('jdp:change', update); });
</script>
<template>
    <span class="ui-date-input">
        <input ref="input" v-bind="attrs" type="text" :value="text" :disabled="disabled || !ready" dir="ltr" inputmode="numeric" autocomplete="off"
            data-buildino-datepicker="1" :data-buildino-date-type="type" data-jdp
            :data-jdp-only-date="type === 'date' ? '' : undefined" :data-jdp-only-time="type === 'time' ? '' : undefined"
            data-jdp-has-second="false" :aria-invalid="invalid || attrs['aria-invalid'] === true || attrs['aria-invalid'] === 'true' || undefined"
            :placeholder="String(attrs.placeholder || (type === 'date' ? '۱۴۰۵/۰۸/۰۷' : type === 'time' ? '۱۴:۳۰' : '۱۴۰۵/۰۸/۰۷ ۱۴:۳۰'))"
            @input="update" @change="update">
        <input v-if="name" type="hidden" :name="name" :value="modelValue" :disabled="disabled">
    </span>
</template>
