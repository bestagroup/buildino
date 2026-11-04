import { createApp } from 'vue';
import UserCreateDialog from './components/management/UserCreateDialog.vue';
import type { CrudField } from './types/management';

export function mountUserCreateDialog(): void {
    const host = document.querySelector<HTMLElement>('[data-user-create-root]');
    const config = (window as Window & { BuildinoOwnershipConfig?: { userFields?: CrudField[]; csrfToken?: string } }).BuildinoOwnershipConfig;
    if (!host || !config) return;
    createApp(UserCreateDialog, { fields: config.userFields || [], csrfToken: config.csrfToken || '' }).mount(host);
}
