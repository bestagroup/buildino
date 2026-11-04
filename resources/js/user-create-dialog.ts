import { createApp } from 'vue';
import UserCreateDialog from './components/management/UserCreateDialog.vue';
import type { CrudField } from './types/management';

export function mountUserCreateDialog(): void {
    const host = document.querySelector<HTMLElement>('[data-user-create-root]');
    const purpose = host?.dataset.userCreatePurpose === 'occupancy' ? 'occupancy' : 'ownership';
    const configs = window as Window & { BuildinoOwnershipConfig?: { userFields?: CrudField[]; csrfToken?: string }; BuildinoOccupancyConfig?: { userFields?: CrudField[]; csrfToken?: string } };
    const config = purpose === 'occupancy' ? configs.BuildinoOccupancyConfig : configs.BuildinoOwnershipConfig;
    if (!host || !config) return;
    createApp(UserCreateDialog, { fields: config.userFields || [], csrfToken: config.csrfToken || '', purpose }).mount(host);
}
