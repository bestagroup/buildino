<script setup lang="ts">
import {
    nextTick,
    onBeforeUnmount,
    useId,
    watch,
} from 'vue';

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

const instanceId = useId();
const titleId = `ui-confirm-title-${instanceId}`;
const messageId = `ui-confirm-message-${instanceId}`;
let returnFocus: HTMLElement | null = null;

const focusableSelector = [
    'button:not([disabled])',
    'a[href]',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const dialogElement = (): HTMLElement | null =>
    document.querySelector(
        `[aria-labelledby="${titleId}"]`,
    );

const requestCancel = (): void => {
    if (!props.busy) {
        emit('cancel');
    }
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        requestCancel();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const dialog = dialogElement();
    const focusable = dialog
        ? [...dialog.querySelectorAll<HTMLElement>(
            focusableSelector,
        )].filter(
            (element) => element.offsetParent !== null,
        )
        : [];

    if (!focusable.length) {
        event.preventDefault();
        dialog?.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (
        event.shiftKey
        && document.activeElement === first
    ) {
        event.preventDefault();
        last.focus();
    } else if (
        !event.shiftKey
        && document.activeElement === last
    ) {
        event.preventDefault();
        first.focus();
    }
};

const detachKeyboardHandler = (): void => {
    document.removeEventListener('keydown', onKeydown);
};

watch(() => props.open, async (open) => {
    if (!open) {
        detachKeyboardHandler();
        returnFocus?.focus({ preventScroll: true });
        returnFocus = null;
        return;
    }

    returnFocus = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    document.addEventListener('keydown', onKeydown);

    await nextTick();

    const dialog = dialogElement();
    const cancel = dialog?.querySelector<HTMLElement>(
        '[data-confirm-cancel]',
    );

    (cancel ?? dialog)?.focus({ preventScroll: true });
});

onBeforeUnmount(() => {
    detachKeyboardHandler();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="ui-dialog-fade">
            <div
                v-if="open"
                class="ui-dialog-layer"
            >
                <button
                    type="button"
                    class="ui-dialog-backdrop"
                    aria-label="انصراف"
                    :disabled="busy"
                    @click="requestCancel"
                />
                <section
                    class="ui-dialog"
                    role="alertdialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :aria-describedby="messageId"
                    :aria-busy="busy"
                    tabindex="-1"
                >
                    <div class="ui-dialog__icon">!</div>
                    <h2 :id="titleId">{{ title }}</h2>
                    <p :id="messageId">{{ message }}</p>
                    <div class="ui-dialog__actions">
                        <button
                            type="button"
                            class="crud-button crud-button--soft"
                            data-confirm-cancel
                            :disabled="busy"
                            @click="requestCancel"
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
