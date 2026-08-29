<script setup lang="ts">
import { nextTick, onBeforeUnmount, useId, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    eyebrow?: string;
    busy?: boolean;
    closeDisabled?: boolean;
}>(), {
    eyebrow: '',
    busy: false,
    closeDisabled: false,
});

const emit = defineEmits<{
    close: [];
}>();

const titleId = `drawer-title-${useId()}`;
let returnFocus: HTMLElement | null = null;

const focusableSelector = [
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'a[href]',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const drawerElement = (): HTMLElement | null =>
    document.querySelector(`[aria-labelledby="${titleId}"]`);

const requestClose = (): void => {
    if (!props.closeDisabled) {
        emit('close');
    }
};

const onKeydown = (event: KeyboardEvent): void => {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        requestClose();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const drawer = drawerElement();
    const focusable = drawer
        ? [...drawer.querySelectorAll<HTMLElement>(focusableSelector)]
            .filter((element) => element.offsetParent !== null)
        : [];

    if (!focusable.length) {
        event.preventDefault();
        drawer?.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const unlockBody = (): void => {
    document.body.classList.remove('ui-overlay-open');
    document.body.style.removeProperty('--ui-scrollbar-compensation');
    document.removeEventListener('keydown', onKeydown);
};

watch(() => props.open, async (open) => {
    if (!open) {
        unlockBody();
        returnFocus?.focus({ preventScroll: true });
        returnFocus = null;
        return;
    }

    returnFocus = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;

    const scrollbarWidth = window.innerWidth
        - document.documentElement.clientWidth;

    document.body.style.setProperty(
        '--ui-scrollbar-compensation',
        `${Math.max(0, scrollbarWidth)}px`,
    );
    document.body.classList.add('ui-overlay-open');
    document.addEventListener('keydown', onKeydown);

    await nextTick();

    const drawer = drawerElement();
    const firstField = drawer?.querySelector<HTMLElement>(
        'input:not([disabled]), select:not([disabled]), textarea:not([disabled])',
    );

    (firstField ?? drawer)?.focus({ preventScroll: true });
});

onBeforeUnmount(unlockBody);
</script>

<template>
    <Teleport to="body">
        <Transition name="ui-drawer-fade">
            <div
                v-if="open"
                class="ui-drawer-layer"
            >
                <button
                    type="button"
                    class="ui-drawer-backdrop"
                    aria-label="بستن پنل"
                    :disabled="closeDisabled"
                    @click="requestClose"
                />

                <aside
                    class="ui-drawer"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :aria-busy="busy"
                    tabindex="-1"
                >
                    <header class="ui-drawer__header">
                        <div>
                            <span
                                v-if="eyebrow"
                                class="eyebrow"
                            >{{ eyebrow }}</span>
                            <h2 :id="titleId">{{ title }}</h2>
                        </div>

                        <button
                            type="button"
                            class="ui-icon-button"
                            aria-label="بستن"
                            :disabled="closeDisabled"
                            @click="requestClose"
                        >×</button>
                    </header>

                    <div class="ui-drawer__body">
                        <slot />
                    </div>

                    <footer
                        v-if="$slots.footer"
                        class="ui-drawer__footer"
                    >
                        <slot name="footer" />
                    </footer>
                </aside>
            </div>
        </Transition>
    </Teleport>
</template>
