((root) => {
    'use strict';
    const positiveId = (value) => /^[1-9]\d*$/.test(String(value ?? ''));
    const same = (a, b) => String(a).trim() === String(b).trim();
    const normalizeMobile = value => {
        let phone = String(value || '').replace(/[۰-۹٠-٩]/g, digit => String('۰۱۲۳۴۵۶۷۸۹'.includes(digit) ? '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit) : '٠١٢٣٤٥٦٧٨٩'.indexOf(digit))).replace(/[\s\-()]/g, '');
        phone = phone.replace(/^(?:\+98|0098|98)(?=9\d{9}$)/, '0');
        return /^9\d{9}$/.test(phone) ? '0' + phone : phone;
    };
    const labelForFloor = (number) => number === 0 ? 'همکف' : number < 0 ? `زیرزمین ${Math.abs(number).toLocaleString('fa-IR')}` : `طبقه ${number.toLocaleString('fa-IR')}`;
    const totals = (blocks) => ({ blocks: blocks.length, floors: blocks.reduce((sum, block) => sum + block.floors.length, 0), units: blocks.reduce((sum, block) => sum + block.floors.reduce((count, floor) => count + floor.units.length, 0), 0) });
    const makeUnit = (block) => ({
        unit_number: '',
        auto_numbered: true,
        area: block.defaultArea ?? '',
        bedrooms: block.defaultBedrooms ?? '',
        usage_type: block.usage || 'residential',
        owner_mobile: '', resident_mobile: '', owner_first_name: '', owner_last_name: '', resident_first_name: '', resident_last_name: '', resident_count: '',
    });

    const renumberGeneratedUnits = (block) => {
        const reserved = new Set(
            block.floors.flatMap((floor) =>
                floor.units
                    .filter(
                        (unit) =>
                            unit.existing
                            || unit.auto_numbered === false
                    )
                    .map((unit) =>
                        String(
                            unit.unit_number
                            ?? ''
                        ).trim()
                    )
                    .filter(Boolean)
            )
        );

        let next = 1;

        for (const floor of block.floors) {
            for (const unit of floor.units) {
                if (
                    unit.existing
                    || unit.auto_numbered === false
                ) {
                    continue;
                }

                while (
                    reserved.has(String(next))
                ) {
                    next++;
                }

                unit.unit_number = String(next);
                reserved.add(String(next));
                next++;
            }
        }
    };
    const resizeUnits = (block, floor, count) => {
        const minimum = floor.units.filter((unit) => unit.existing).length;
        const target = Math.max(minimum, Math.min(100, Math.trunc(Number(count) || 0)));
        while (floor.units.length > target) {
            const index = floor.units.findLastIndex((unit) => !unit.existing);
            if (index < 0) break;
            floor.units.splice(index, 1);
        }
        while (floor.units.length < target) {
            floor.units.push(makeUnit(block));
        }

        renumberGeneratedUnits(block);
    };
    const resizeFloors = (block, count) => {
        const minimum = block.floors.filter((floor) => floor.existing).length;
        const target = Math.max(1, minimum, Math.min(100, Math.trunc(Number(count) || 1)));
        while (block.floors.length > target) {
            const index = block.floors.findLastIndex((floor) => !floor.existing);
            if (index < 0) break;
            block.floors.splice(index, 1);
        }
        while (block.floors.length < target) {
            const number = block.floors.length
                ? Math.max(
                    ...block.floors.map(
                        (floor) => floor.number
                    )
                ) + 1
                : Number(block.startFloor ?? 1);
            const floor = { number, title: labelForFloor(number), units: [] };
            block.floors.push(floor);
            resizeUnits(block, floor, block.unitsPerFloor ?? 2);
        }

        renumberGeneratedUnits(block);
    };
    const validate = (draft) => {
        const errors = [];
        if (!['existing', 'new'].includes(draft.mode)) errors.push({ step: 0, message: 'روش شروع را انتخاب کنید.' });
        if (draft.mode === 'existing' && !positiveId(draft.buildingId)) errors.push({ step: 0, field: 'buildingId', message: 'ساختمان را انتخاب کنید.' });
        if (draft.mode === 'existing' && draft.updateExisting) {
            if (!draft.existingTitle?.trim() || draft.existingTitle.length > 255) errors.push({ step: 0, field: 'existingTitle', message: 'نام ساختمان یا بلوک را وارد کنید.' });
            if ((draft.existingPostalCode || '').length > 20 || (draft.existingAddress || '').length > 2000) errors.push({ step: 0, field: 'existingAddress', message: 'طول نشانی یا کد پستی معتبر نیست.' });
            for (const field of ['existingParkingCount', 'existingStorageCount']) if (!Number.isInteger(Number(draft[field] || 0)) || Number(draft[field] || 0) < 0) errors.push({ step: 0, field, message: 'تعداد پارکینگ و انباری باید عدد صحیح و نامنفی باشد.' });
        }
        if (draft.mode === 'new') {
            if (!['standalone', 'existing', 'new'].includes(draft.complexMode)) errors.push({ step: 0, field: 'complexMode', message: 'وضعیت مجتمع را انتخاب کنید.' });
            if (!/^[a-zA-Z0-9-]{16,50}$/.test(String(draft.token || ''))) errors.push({ step: 0, message: 'پیش‌نویس معتبر نیست؛ یک راه‌اندازی تازه شروع کنید.' });
            if (!draft.title?.trim() || draft.title.length > 255) errors.push({ step: 0, field: 'title', message: 'نام ساختمان را وارد کنید (حداکثر ۲۵۵ حرف).' });
            if (draft.complexMode === 'existing' && !positiveId(draft.complexId)) errors.push({ step: 0, field: 'complexId', message: 'مجتمع را انتخاب کنید.' });
            if (draft.complexMode !== 'existing') {
                if (!draft.province || !draft.city) errors.push({ step: 0, field: 'provinceId', message: 'استان و شهر ساختمان را انتخاب کنید.' });
                if (draft.complexMode === 'new' && (!draft.complexTitle?.trim() || draft.complexTitle.length > 255)) errors.push({ step: 0, field: 'complexTitle', message: 'نام مجتمع را وارد کنید.' });
            }
            if ((draft.postalCode || '').length > 20) errors.push({ step: 0, field: 'postalCode', message: 'کد پستی حداکثر ۲۰ رقم دارد.' });
            for (const field of ['parkingCount', 'storageCount']) if (!Number.isInteger(Number(draft[field] || 0)) || Number(draft[field] || 0) < 0) errors.push({ step: 0, field, message: 'تعداد پارکینگ و انباری باید عدد صحیح و بزرگ‌تر یا مساوی صفر باشد.' });
        }
        if (!Array.isArray(draft.blocks) || !draft.blocks.length || draft.blocks.length > 20) return [...errors, { step: 1, message: 'بین ۱ تا ۲۰ بلوک تعریف کنید.' }];
        const titles = new Set();
        for (const block of draft.blocks) {
            const title = block.title?.trim();
            if (!title || title.length > 255 || titles.has(title)) errors.push({ step: 1, message: 'نام بلوک‌ها باید مشخص و متفاوت باشد.' });
            titles.add(title);
            if (!block.floors.length || block.floors.length > 100) errors.push({ step: 1, message: `تعداد طبقات ${title} باید بین ۱ تا ۱۰۰ باشد.` });
            const numbers = new Set();
            for (const floor of block.floors) {
                if (!Number.isInteger(floor.number) || numbers.has(floor.number)) errors.push({ step: 1, message: `شماره طبقات ${title} تکراری یا نامعتبر است.` });
                numbers.add(floor.number);
                const units = new Set();
                for (const unit of floor.units) {
                    const number = String(unit.unit_number ?? '').trim();
                    if (!number || number.length > 100 || units.has(number)) errors.push({ step: 2, message: `شماره واحدهای ${title}، ${floor.title} را بررسی کنید؛ شماره‌ها نباید در یک طبقه تکراری باشند.` });
                    units.add(number);
                    const ownerPhone = normalizeMobile(unit.owner_mobile), residentPhone = normalizeMobile(unit.resident_mobile);
                    for (const [kind, label, phone] of [['owner', 'مالک', ownerPhone], ['resident', 'ساکن', residentPhone]]) {
                        if (!phone) continue;
                        if (!/^09\d{9}$/.test(phone)) errors.push({ step: 2, message: `شماره موبایل ${label} واحد ${number} معتبر نیست.` });
                        if (kind === 'resident' && phone === ownerPhone) continue;
                        if (['first_name', 'last_name'].some(key => !String(unit[`${kind}_${key}`] || '').trim() || String(unit[`${kind}_${key}`]).length > 100)) errors.push({ step: 2, message: `نام و نام خانوادگی ${label} واحد ${number} را تکمیل کنید.` });
                    }
                    if (residentPhone && (!String(unit.resident_count ?? '').trim() || !Number.isInteger(Number(unit.resident_count)) || Number(unit.resident_count) < 1 || Number(unit.resident_count) > 1000)) errors.push({ step: 2, message: `تعداد ساکنان واحد ${number} باید بین ۱ تا ۱۰۰۰ باشد.` });
                    if (unit.area !== '' && unit.area != null && (!Number.isFinite(Number(unit.area)) || Number(unit.area) < 0)) errors.push({ step: 2, message: 'متراژ واحد باید عددی بزرگ‌تر یا مساوی صفر باشد.' });
                    if (unit.bedrooms !== '' && unit.bedrooms != null && (!Number.isInteger(Number(unit.bedrooms)) || Number(unit.bedrooms) < 0 || Number(unit.bedrooms) > 50)) errors.push({ step: 2, message: 'تعداد اتاق‌ها باید عددی بین صفر و ۵۰ باشد.' });
                    if (!['residential', 'commercial', 'office', 'other'].includes(unit.usage_type)) errors.push({ step: 2, message: 'کاربری واحد معتبر نیست.' });
                }
            }
        }
        if (!totals(draft.blocks).units) errors.push({ step: 2, message: 'حداقل یک واحد برای ساختمان تعریف کنید.' });
        if (totals(draft.blocks).units > 2000) errors.push({ step: 1, message: 'در هر بار راه‌اندازی حداکثر ۲۰۰۰ واحد اضافه کنید.' });
        return errors;
    };
    const list = async (api, path) => {
        const rows = [];
        const seen = new Set();
        while (path) {
            if (seen.has(path)) throw new Error('دریافت فهرست کامل نشد؛ دوباره تلاش کنید.');
            seen.add(path);
            const result = await api(path);
            if (!Array.isArray(result?.data)) throw new Error('پاسخ فهرست معتبر نیست؛ دوباره تلاش کنید.');
            rows.push(...result.data);
            path = result.links?.next || null;
        }
        return rows;
    };
    const readStructure = async (api, buildingId) => {
        const blocks = await list(api, `buildings/${buildingId}/blocks?per_page=100`);
        const result = [];
        for (const block of blocks) {
            const floors = await list(api, `blocks/${block.id}/floors?per_page=100`);
            const entry = { title: block.title, existing: true, id: block.id, unitsPerFloor: 2, startFloor: 1, defaultArea: '', defaultBedrooms: '', usage: 'residential', floors: [] };
            for (const floor of floors) {
                const units = await list(api, `floors/${floor.id}/units?per_page=100`);
                entry.floors.push({ number: Number(floor.floor_number), title: floor.title || labelForFloor(Number(floor.floor_number)), existing: true, id: floor.id, units: units.map((unit) => ({ id: unit.id, unit_number: unit.unit_number, auto_numbered: false, usage_type: unit.usage_type, existing: true, area: unit.area ?? '', bedrooms: unit.bedrooms ?? '' })) });
            }
            entry.floors.sort((a, b) => a.number - b.number);
            entry.startFloor =
                entry.floors[0]?.number ?? 1;
            result.push(entry);
        }
        return result;
    };
    // Every explicit run reads current records before writes. A lost response
    // is recovered by stable codes and parent-scoped natural keys, not re-posted.
    const execute = async (draft, api, progress = () => {}) => {
        const people = [];
        const problems = validate(draft);
        if (problems.length) throw new Error(problems[0].message);
        const counts = totals(draft.blocks);
        const total = counts.blocks + counts.floors + counts.units + (draft.mode === 'new' ? (draft.complexMode === 'existing' ? 1 : 2) : 0);
        let done = 0;
        let created = 0;
        let firstUnitId = null;
        const tick = (label, fresh = false) => { if (fresh) created++; progress({ done: ++done, total, label, created }); };
        const create = async (path, payload) => {
            const result = await api(path, { method: 'POST', body: JSON.stringify(payload) });
            if (!positiveId(result?.data?.id)) throw new Error('نتیجه ثبت مشخص نیست. برای بررسی و ادامه، دکمه ادامه ثبت را بزنید.');
            return result.data;
        };
        let building;
        if (draft.mode === 'existing') {
            building = (await api(`buildings/${draft.buildingId}`)).data;
            if (!positiveId(building?.id) || !same(building.id, draft.buildingId)) throw new Error('ساختمان انتخاب‌شده در دسترس نیست.');
        } else {
            let complexId = draft.complexId;
            if (draft.complexMode !== 'existing') {
                const code = `SET-C-${draft.token}`;
                let complex = (await list(api, `complexes?search=${encodeURIComponent(code)}&per_page=100`)).find((row) => row.code === code);
                const fresh = !complex;
                if (!complex) complex = await create('complexes', { code, workspace_type: draft.complexMode === 'standalone' ? 'standalone' : 'complex', title: draft.complexMode === 'standalone' ? draft.title.trim() : draft.complexTitle.trim(), province: draft.province, city: draft.city, address: draft.address || null, postal_code: draft.postalCode || null, is_active: true });
                complexId = complex.id;
                tick('محل ساختمان', fresh);
            }
            const code = `SET-B-${draft.token}`;
            building = (await list(api, `buildings?search=${encodeURIComponent(code)}&per_page=100`)).find((row) => row.code === code);
            const fresh = !building;
            if (!building) building = await create('buildings', { complex_id: complexId, ...(draft.complexMode !== 'standalone' ? { structure_type: 'complex_block' } : {}), code, title: draft.title.trim(), address: draft.address || null, postal_code: draft.postalCode || null, timezone: 'Asia/Tehran', currency: 'IRR', floors_count: counts.floors, units_count: counts.units, parking_count: Number(draft.parkingCount || 0), storage_count: Number(draft.storageCount || 0), is_active: true });
            if (!same(building.complex_id, complexId)) throw new Error('ساختمان ذخیره‌شده با مجتمع این راه‌اندازی مطابقت ندارد.');
            tick('ساختمان', fresh);
        }
        const blocks = await list(api, `buildings/${building.id}/blocks?per_page=100`);
        for (const plannedBlock of draft.blocks) {
            let block = plannedBlock.existing ? blocks.find((row) => same(row.id, plannedBlock.id)) : blocks.find((row) => same(row.title, plannedBlock.title));
            if (plannedBlock.existing && !block) throw new Error('یک بلوک ثبت‌شده دیگر در دسترس نیست؛ راه‌اندازی تازه‌ای با اطلاعات فعلی ساختمان شروع کنید.');
            const freshBlock = !block;
            if (!block) { block = await create(`buildings/${building.id}/blocks`, { title: plannedBlock.title.trim(), sort_order: blocks.length, is_active: true }); blocks.push(block); }
            tick(plannedBlock.title, freshBlock);
            const floors = await list(api, `blocks/${block.id}/floors?per_page=100`);
            for (const plannedFloor of plannedBlock.floors) {
                let floor = plannedFloor.existing ? floors.find((row) => same(row.id, plannedFloor.id)) : floors.find((row) => Number(row.floor_number) === plannedFloor.number);
                if (plannedFloor.existing && !floor) throw new Error('یک طبقه ثبت‌شده دیگر در دسترس نیست؛ اطلاعات فعلی ساختمان را دوباره انتخاب کنید.');
                const freshFloor = !floor;
                if (!floor) { floor = await create(`blocks/${block.id}/floors`, { floor_number: plannedFloor.number, title: plannedFloor.title || labelForFloor(plannedFloor.number), sort_order: floors.length }); floors.push(floor); }
                tick(`${plannedBlock.title}، ${plannedFloor.title}`, freshFloor);
                const units = await list(api, `floors/${floor.id}/units?per_page=100`);
                for (const plannedUnit of plannedFloor.units) {
                    let unit = plannedUnit.existing ? units.find((row) => same(row.id, plannedUnit.id)) : units.find((row) => same(row.unit_number, plannedUnit.unit_number));
                    if (plannedUnit.existing && !unit) throw new Error('یک واحد ثبت‌شده دیگر در دسترس نیست؛ اطلاعات فعلی ساختمان را دوباره انتخاب کنید.');
                    const freshUnit = !unit;
                    if (!unit) {
                        unit = await create(`floors/${floor.id}/units`, { unit_number: String(plannedUnit.unit_number).trim(), area: plannedUnit.area === '' || plannedUnit.area == null ? null : Number(plannedUnit.area), bedrooms: plannedUnit.bedrooms === '' || plannedUnit.bedrooms == null ? null : Number(plannedUnit.bedrooms), usage_type: plannedUnit.usage_type, is_active: true });
                        units.push(unit);
                    }
                    firstUnitId ||= unit.id;
                    const owner = normalizeMobile(plannedUnit.owner_mobile), resident = normalizeMobile(plannedUnit.resident_mobile);
                    for (const kind of ['owner', 'resident']) {
                        const mobile = kind === 'owner' ? owner : resident;
                        if (!mobile || kind === 'resident' && resident === owner) continue;
                        const relation = kind === 'owner' && owner === resident ? 'owner_resident' : kind;
                        people.push({ unit_id: unit.id, unit_number: String(unit.unit_number), mobile,
                            first_name: String(plannedUnit[`${kind}_first_name`] || '').trim(), last_name: String(plannedUnit[`${kind}_last_name`] || '').trim(),
                            relation, resident_count: relation === 'owner' ? null : Number(plannedUnit.resident_count) });
                    }
                    tick(`واحد ${plannedUnit.unit_number}، ${plannedFloor.title}`, freshUnit);
                }
            }
        }
        if (draft.mode === 'existing' && draft.updateExisting) {
            const values = { title: draft.existingTitle.trim(), address: draft.existingAddress || null, postal_code: draft.existingPostalCode || null, parking_count: Number(draft.existingParkingCount || 0), storage_count: Number(draft.existingStorageCount || 0) };
            // Do not overwrite concurrent edits to fields this user never changed.
            const payload = Object.fromEntries(Object.entries(values).filter(([key, value]) => String(value ?? '') !== String((draft.existingOriginal ? draft.existingOriginal[key] : building.location?.[key] ?? building.statistics?.[key] ?? building[key]) ?? '')));
            if (Object.keys(payload).length) {
                await api(`buildings/${building.id}`, { method: 'PATCH', body: JSON.stringify(payload) });
            }
        }
        return { buildingId: building.id, complexId: building.complex_id, firstUnitId, created, people, ...counts };
    };
    root.BuildinoBuildingSetupEngine = Object.freeze({ labelForFloor, totals, resizeUnits, resizeFloors, renumberGeneratedUnits, normalizeMobile, validate, execute, readStructure, list });
})(typeof window === 'undefined' ? globalThis : window);
