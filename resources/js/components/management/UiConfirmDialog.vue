<script setup lang="ts">
import { nextTick, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    message: string;
    busy?: boolean;
    confirmLabel?: string;
}>(), {
    busy: false,
    confirmLabel: 'تأیید',
});

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

watch(() => props.open, async (open) => {
    if (!open) {
        return;
    }

    await nextTick();
    document.querySelector<HTMLElement>('[data-confirm-cancel]')?.focus();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="ui-dialog-fade">
            <div
                v-if="open"
                class="ui-dialog-layer"
                @keydown.esc.prevent="!busy && emit('cancel')"
            >
                <button
                    type="button"
                    class="ui-dialog-backdrop"
                    aria-label="انصراف"
                    :disabled="busy"
                    @click="emit('cancel')"
                />
                <section
                    class="ui-dialog"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="ui-confirm-title"
                >
                    <div class="ui-dialog__icon">!</div>
                    <h2 id="ui-confirm-title">{{ title }}</h2>
                    <p>{{ message }}</p>
                    <div class="ui-dialog__actions">
                        <button
                            type="button"
                            class="crud-button crud-button--soft"
                            data-confirm-cancel
                            :disabled="busy"
                            @click="emit('cancel')"
                        >انصراف</button>
                        <button
                            type="button"
                            class="crud-button ui-button--danger"
                            :disabled="busy"
                            @click="emit('confirm')"
                        >{{ busy ? 'در حال انجام...' : confirmLabel }}</button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
