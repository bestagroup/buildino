import { nextTick, type ObjectDirective } from 'vue';
import '../buildino-select2.js';

type Adapter = { enhance(root: Element): void; refresh(select: HTMLSelectElement): void; destroy(root: Element): void };
const adapter = () => (window as Window & { BuildinoSelect2?: Adapter }).BuildinoSelect2;
const readyHandlers = new WeakMap<HTMLSelectElement, () => void>();

export const vSelect2: ObjectDirective<HTMLSelectElement> = {
    mounted(select) {
        const refresh = () => { if (select.isConnected) adapter()?.refresh(select); };
        readyHandlers.set(select, refresh);
        window.addEventListener('buildino:select2-ready', refresh);
        void nextTick(refresh);
    },
    updated(select) {
        void nextTick(() => { if (select.isConnected) adapter()?.refresh(select); });
    },
    beforeUnmount(select) {
        const handler = readyHandlers.get(select);
        if (handler) window.removeEventListener('buildino:select2-ready', handler);
        readyHandlers.delete(select);
        adapter()?.destroy(select);
    },
};
