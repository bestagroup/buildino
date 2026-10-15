import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = vm.createContext({});
vm.runInContext(readFileSync(new URL('../../public/js/buildino-building-setup-engine.js', import.meta.url), 'utf8'), context);
const engine = context.BuildinoBuildingSetupEngine;
const draft = () => {
    const block = { title: 'Main', startFloor: 0, unitsPerFloor: 2, defaultArea: 85, defaultBedrooms: 2, usage: 'residential', floors: [] };
    engine.resizeFloors(block, 2);
    return { mode: 'new', token: '12345678-1234-1234-1234-123456789abc', title: 'Building', complexMode: 'standalone', province: 'تهران', city: 'تهران', blocks: [block] };
};
const memoryApi = () => {
    const data = { complexes: [], buildings: [], blocks: [], floors: [], units: [] };
    const writes = [];
    let lost = false;
    let deny = false;
    const api = async (path, options = {}) => {
        const url = new URL(path, 'http://localhost/');
        const segments = url.pathname.split('/').filter(Boolean);
        const table = segments.at(-1);
        if (options.method === 'POST') {
            if (deny && table === 'units') throw new Error('Forbidden');
            const row = { ...JSON.parse(options.body), id: data[table].length + 1 };
            const parent = { blocks: 'building_id', floors: 'block_id', units: 'floor_id' }[table];
            if (parent) row[parent] = Number(segments.at(-2));
            data[table].push(row); writes.push(table);
            if (lost && table === 'units') { lost = false; throw new Error('Connection lost after commit'); }
            return { data: row };
        }
        if (/^\d+$/.test(table)) return { data: data[segments[0]].find((row) => row.id === Number(table)) };
        let rows = data[table];
        const parent = { blocks: 'building_id', floors: 'block_id', units: 'floor_id' }[table];
        if (parent) rows = rows.filter((row) => row[parent] === Number(segments.at(-2)));
        if (url.searchParams.has('search')) rows = rows.filter((row) => row.code === url.searchParams.get('search'));
        return { data: rows, links: { next: null } };
    };
    return { data, writes, api, loseNextUnit: () => { lost = true; }, denyUnits: () => { deny = true; } };
};

test('bulk generation preserves edited and existing rows when counts change', () => {
    const plan = draft(); const block = plan.blocks[0];
    assert.equal(engine.totals(plan.blocks).units, 4);
    block.floors[0].units[0].existing = true;
    block.floors[0].units[0].unit_number = 'A-1';
    block.floors[0].units[1].area = 123;
    engine.resizeUnits(block, block.floors[0], 3);
    assert.equal(block.floors[0].units[1].area, 123);
    engine.resizeUnits(block, block.floors[0], 0);
    assert.equal(block.floors[0].units.length, 1);
    assert.equal(block.floors[0].units[0].unit_number, 'A-1');
    block.floors[0].existing = true;
    engine.resizeFloors(block, 0);
    assert.equal(block.floors.length, 1);
    assert.equal(block.floors[0].units[0].unit_number, 'A-1');
});

test('duplicate numbers are rejected inside their floor and allowed in different floors', () => {
    const plan = draft();
    plan.blocks[0].floors[1].units[0].unit_number = plan.blocks[0].floors[0].units[0].unit_number;
    assert.equal(engine.validate(plan).length, 0);
    plan.blocks[0].floors[0].units[1].unit_number = plan.blocks[0].floors[0].units[0].unit_number;
    assert.equal(engine.validate(plan)[0].step, 2);
});

test('one explicit run creates the hierarchy and a second run does not duplicate it', async () => {
    const server = memoryApi(); const plan = draft();
    const result = await engine.execute(plan, server.api);
    assert.equal(result.units, 4); assert.equal(result.created, 9);
    assert.equal(server.data.buildings[0].units_count, 4);
    assert.equal(server.data.units[0].area, 85);
    const writes = server.writes.length;
    const repeated = await engine.execute(plan, server.api);
    assert.equal(repeated.created, 0); assert.equal(server.writes.length, writes);
});

test('a committed write with a lost response stops and is reconciled before explicit resume', async () => {
    const server = memoryApi(); const plan = draft(); server.loseNextUnit();
    await assert.rejects(engine.execute(plan, server.api), /Connection lost/);
    assert.equal(server.data.units.length, 1);
    const resumed = await engine.execute(plan, server.api);
    assert.equal(resumed.units, 4); assert.equal(server.data.units.length, 4);
    assert.equal(server.data.buildings.length, 1); assert.equal(server.data.complexes.length, 1);
});

test('permission failure stops subsequent writes without automatically retrying', async () => {
    const server = memoryApi(); server.denyUnits();
    await assert.rejects(engine.execute(draft(), server.api), /Forbidden/);
    assert.equal(server.data.units.length, 0);
    assert.equal(server.data.floors.length, 1);
});

test('existing child identifiers must still belong to their selected parent', async () => {
    const server = memoryApi(); const plan = draft();
    await engine.execute(plan, server.api);
    const existing = await engine.readStructure(server.api, 1);
    existing[0].floors[0].units[0].id = 999;
    const writes = server.writes.length;
    await assert.rejects(engine.execute({ ...plan, mode: 'existing', buildingId: 1, blocks: existing }, server.api), /واحد/);
    assert.equal(server.writes.length, writes);
});

test('all pages are read before treating a record as absent', async () => {
    const calls = [];
    const rows = await engine.list(async (url) => {
        calls.push(url);
        return url === 'first' ? { data: [{ id: 1 }], links: { next: 'second' } } : { data: [{ id: 2 }], links: { next: null } };
    }, 'first');
    assert.equal(rows.length, 2); assert.deepEqual(calls, ['first', 'second']);
});
