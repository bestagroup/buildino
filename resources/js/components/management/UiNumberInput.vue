<script setup lang="ts">
import { ref, watch, onMounted, useAttrs, nextTick } from 'vue';
import '../../../../public/js/buildino-numeric.js';

defineOptions({ inheritAttrs: false });
const props = defineProps<{ modelValue?: string | number | null; min?: string | number; max?: string | number; step?: string | number }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const numeric = (window as unknown as { BuildinoNumeric: {
    normalize(value: unknown): string; format(value: unknown): string; display(input: HTMLInputElement): void; validate(input: HTMLInputElement, value?: unknown): boolean;
} }).BuildinoNumeric;
const attrs = useAttrs(), input = ref<HTMLInputElement | null>(null), text = ref(numeric.format(props.modelValue));
let lastEmitted: string | null = null;
const update = (event: Event) => {
    if ((event as InputEvent).isComposing) return;
    const field = event.target as HTMLInputElement;
    lastEmitted = numeric.normalize(field.value);
    numeric.display(field);
    text.value = field.value;
    emit('update:modelValue', lastEmitted);
};
watch(() => [props.modelValue, props.min, props.max, props.step], async () => {
    if (String(props.modelValue ?? '') !== lastEmitted) text.value = numeric.format(props.modelValue);
    await nextTick();
    if (input.value) numeric.validate(input.value, props.modelValue);
}, { flush: 'post' });
onMounted(() => { if (input.value) numeric.validate(input.value); });
</script>
<template>
    <input ref="input" v-bind="attrs" type="text" dir="ltr" :inputmode="step && String(step) !== '1' ? 'decimal' : 'numeric'"
        data-number-managed="true" :min="min" :max="max" :step="step" :value="text"
        @input="update" @change="update" @compositionend="update">
</template>
