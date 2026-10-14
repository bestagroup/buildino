/// <reference types="vite/client" />

interface Window {
    BuildinoCoreFlows?: {
        mount(key: string): Promise<void>;
        saved(key: string, record: Record<string, unknown>, mode: string): void;
        defaults(key: string, values: Record<string, unknown>, mode: string): Record<string, unknown>;
        setRows(records: Record<string, unknown>[]): void;
        allowed(key: string, action: string): boolean;
        labels: Record<string, string>;
    };
}

declare module '*.vue' {
    import type { DefineComponent } from 'vue';

    const component: DefineComponent<object, object, unknown>;

    export default component;
}
