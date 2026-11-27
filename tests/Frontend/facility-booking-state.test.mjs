import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const create = (request, change = () => {}) => {
    const context = { window: {} }; vm.createContext(context);
    vm.runInContext(readFileSync(new URL('../../public/js/buildino-facility-booking.js', import.meta.url), 'utf8'), context);
    return context.window.BuildinoFacilityBooking.create({ request, change });
};
test('shared booking discards late responses and only accepts current available slots', async () => {
    let release;
    const picker = create(url => url.includes('2099-01-01') ? new Promise(resolve => { release = resolve; }) : Promise.resolve({ data: { facility_is_active: true, slots: [{ id: 2, is_available: true, price: 5 }, { id: 3, is_available: false }], date: '2099-01-02' } }));
    const first = picker.load({ id: 1 }, '2099-01-01');
    await picker.load({ id: 1 }, '2099-01-02');
    picker.pick(3); assert.equal(picker.selection, null);
    picker.pick(2); assert.equal(picker.selection.slotId, 2);
    release({ data: { facility_is_active: true, slots: [], date: '2099-01-01' } }); await first;
    assert.equal(picker.selection.date, '2099-01-02');
    picker.reset(); assert.equal(picker.selection, null);
});
test('manual time compatibility requires an active facility without slots; errors clear selection', async () => {
    let mode = 'manual';
    const picker = create(async () => {
        if (mode === 'error') throw new Error('offline');
        return { data: { facility_is_active: mode !== 'inactive', slots: mode === 'full' ? [{ id: 1, is_available: false }] : [], schedules: [] } };
    });
    await picker.load({ id: 1, default_price: 17 }, '2099-01-01');
    picker.manual('10:00', '09:00'); assert.equal(picker.selection, null);
    picker.manual('09:00', '10:00'); assert.equal(picker.selection.price, 17);
    for (const current of ['full', 'inactive', 'error']) {
        mode = current; await picker.load({ id: 1 }, '2099-01-01'); picker.manual('09:00', '10:00'); assert.equal(picker.selection, null);
    }
});
