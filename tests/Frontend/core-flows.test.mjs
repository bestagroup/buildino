import { readFileSync } from 'node:fs';
import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/buildino-core-flows.js', import.meta.url), 'utf8');
const structure = ['complexes', 'buildings', 'blocks', 'floors', 'units'];
const resources = Object.fromEntries([...structure, 'users', 'roles', 'role-assignments', 'invitations'].map((key) => [key, { url: `/management/operations/${key}` }]));
function browser({ flow = 'P06', actorId = 1, storage = new Map(), permissions = [], globalPermissions = [], fetchImpl } = {}) {
    let href = `http://localhost/management/operations/blocks?flow=${flow}`;
    const calls = [];
    const context = {
        URL, console, Date,
        sessionStorage: { getItem: (key) => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) },
        document: { querySelector: () => null, addEventListener: () => {} },
        window: { BuildinoFlowConfig: { actorId, resources, permissions, globalPermissions, csrfToken: 'test' }, location: { get href() { return href; }, origin: 'http://localhost' } },
        fetch: async (url, options) => {
            calls.push({ url: String(url), ...options });
            return fetchImpl ? fetchImpl(url, options) : { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ data: { id: Number(String(url).split('/').at(-1)) } }) };
        },
    };
    vm.runInNewContext(source, context);
    return { core: context.window.BuildinoCoreFlows, storage, calls, go: (path) => { href = `http://localhost${path}`; }, state: () => JSON.parse(storage.get(`buildino.core-flows.${actorId}`) || '{}') };
}

test('changing a structure parent removes every selected descendant', async () => {
    const app = browser();
    app.core.saved('buildings', { id: 2, complex_id: 1 }, 'create');
    app.core.saved('blocks', { id: 3, building_id: 2 }, 'create');
    app.core.saved('floors', { id: 4, block_id: 3 }, 'create');
    app.core.saved('units', { id: 5, floor_id: 4 }, 'create');
    app.core.contextChanged('floors', 9);
    assert.deepEqual(app.state().P06.ids, { complexes: 1, buildings: 2, blocks: 9 });
    assert.equal(app.core.parentValue('floors'), 9);
    assert.equal(app.core.parentValue('units'), undefined);
});

test('saved IDs survive navigation and permission failure invalidates stale progress', async () => {
    const storage = new Map();
    const first = browser({ storage });
    first.core.saved('buildings', { id: 2, complex_id: 1 }, 'create');
    const second = browser({ storage, fetchImpl: async () => ({ ok: false, status: 403, headers: { get: () => 'application/json' }, json: async () => ({}) }) });
    await second.core.mount('blocks');
    assert.deepEqual(second.state().P06.ids, {});
    assert.equal(second.calls.length, 1);
    assert.equal(second.calls[0].credentials, 'same-origin');
});

test('guided user creation remains inactive until a separate activation; scoped creation is unchanged', () => {
    const admin = browser({ flow: 'P05', globalPermissions: ['users.create', 'users.update'] });
    assert.equal(admin.core.defaults('users', { is_active: true }, 'create').is_active, false);
    admin.core.saved('users', { id: 12, is_active: false }, 'create');
    assert.equal(admin.core.defaults('role-assignments', {}, 'create').user_id, 12);
    admin.core.saved('role-assignments', { id: 4, user_id: 13 }, 'create');
    assert.equal(admin.state().P05.assignmentId, undefined);
    admin.core.saved('role-assignments', { id: 5, user_id: 12 }, 'create');
    assert.equal(admin.state().P05.assignmentId, 5);
    assert.equal(admin.state().P05.active, false);
    admin.core.saved('role-assignments', { id: 5, user_id: 12, is_active: false }, 'edit');
    assert.equal(admin.state().P05.assignmentId, undefined);
    assert.equal(admin.calls.length, 0);
    const scoped = browser({ flow: 'P05', permissions: ['users.create', 'users.update'] });
    assert.equal(scoped.core.defaults('users', { is_active: true }, 'create').is_active, true);
    assert.equal(scoped.core.allowed('role-assignments', 'create'), false);
});

test('invitation actions and assignment creation use their real existing permissions', () => {
    const app = browser({ permissions: ['unit-invitations.create'], globalPermissions: ['users.update'] });
    assert.equal(app.core.allowed('invitations', 'create'), true);
    assert.equal(app.core.allowed('invitations', 'update'), false);
    assert.equal(app.core.allowed('role-assignments', 'create'), true);
});

test('progress is isolated by actor and corrupt storage cannot break mounting', async () => {
    const storage = new Map([['buildino.core-flows.1', '"bad"']]);
    const first = browser({ storage });
    first.core.saved('buildings', { id: 2, complex_id: 1 }, 'create');
    const second = browser({ storage, actorId: 2 });
    assert.equal(second.core.parentValue('blocks'), undefined);
    await second.core.mount('blocks');
    assert.deepEqual(second.state().P06.ids, {});
});

test('network failure neither advances a step nor retries a write automatically', async () => {
    const app = browser({ fetchImpl: async () => { throw new Error('offline'); } });
    app.core.saved('buildings', { id: 2, complex_id: 1 }, 'create');
    await assert.rejects(app.core.request('/management/data/users/9', { method: 'PATCH', body: '{}' }), /offline/);
    assert.equal(app.calls.length, 1);
    assert.deepEqual(app.state().P06.ids, { complexes: 1, buildings: 2 });
    await assert.rejects(app.core.request('https://other.example/api'), /آدرس درخواست/);
    assert.equal(app.calls.length, 1);
});
