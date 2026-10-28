import { computed, ref, type Ref } from 'vue';
import { structure, type StructureKey, type StructureUi } from './building-structure';

/** Keep every selection and link inside the same building/block/floor branch. */
export function useStructureScope(key: StructureKey, ui: StructureUi, contextId: Ref<string>) {
    const meta = structure[key];
    const buildingId = ref(''), blockId = ref('');
    const buildings = computed(() => ui.hierarchy?.buildings ?? ui.contexts
        .filter((row, index, all) => all.findIndex(item => item.building_id === row.building_id) === index)
        .map(row => ({ id: row.building_id, title: row.label.split(' / ')[0], complex_id: row.complex_id })));
    const blocks = computed(() => (key === 'floors'
        ? ui.contexts.map(row => ({ id: row.id, title: row.title || row.label, building_id: row.building_id }))
        : ui.hierarchy?.blocks ?? ui.contexts
            .filter((row, index, all) => row.block_id && all.findIndex(item => item.block_id === row.block_id) === index)
            .map(row => ({ id: row.block_id!, title: row.label, building_id: row.building_id })))
        .filter(row => String(row.building_id) === buildingId.value));
    const floors = computed(() => ui.contexts.filter(row => String(row.building_id) === buildingId.value && String(row.block_id) === blockId.value));
    const context = computed(() => ui.contexts.find(row => String(row.id) === contextId.value
        && String(row.building_id) === buildingId.value
        && (key !== 'units' || String(row.block_id) === blockId.value)));
    const selectBlock = () => {
        contextId.value = key === 'floors' ? blockId.value : floors.value.length === 1 ? String(floors.value[0].id) : '';
    };
    const selectBuilding = () => {
        blockId.value = '';
        contextId.value = key === 'blocks' ? buildingId.value : '';
        if (key !== 'blocks' && blocks.value.length === 1) { blockId.value = String(blocks.value[0].id); selectBlock(); }
    };
    const initialize = (query: URLSearchParams) => {
        if (!meta.context) return;
        const requested = query.get(meta.context), ancestorBuilding = query.get('building_id'), ancestorBlock = query.get('block_id');
        const target = ui.contexts.find(row => String(row.id) === requested);
        const blockBuilding = key === 'units' && ancestorBlock
            ? ui.hierarchy?.blocks.find(row => String(row.id) === ancestorBlock)?.building_id
                ?? ui.contexts.find(row => String(row.block_id) === ancestorBlock)?.building_id
            : undefined;
        buildingId.value = buildings.value.find(row => String(row.id) === (ancestorBuilding || String(blockBuilding ?? target?.building_id)))?.id.toString()
            ?? (!ancestorBuilding && !requested && buildings.value.length === 1 ? String(buildings.value[0].id) : '');
        if (key === 'blocks') { contextId.value = buildingId.value; return; }
        blockId.value = blocks.value.find(row => String(row.id) === (ancestorBlock || String(target?.block_id)))?.id.toString()
            ?? (!ancestorBlock && !requested && blocks.value.length === 1 ? String(blocks.value[0].id) : '');
        if (key === 'floors') { contextId.value = blockId.value; return; }
        contextId.value = floors.value.find(row => String(row.id) === requested)?.id.toString()
            ?? (!requested && floors.value.length === 1 ? String(floors.value[0].id) : '');
    };
    const query = () => {
        const params = new URLSearchParams();
        const building = buildings.value.find(row => String(row.id) === buildingId.value);
        if (building?.complex_id) params.set('complex_id', String(building.complex_id));
        if (buildingId.value) params.set('building_id', buildingId.value);
        if (key !== 'blocks' && blockId.value) params.set('block_id', blockId.value);
        if (key === 'units' && context.value) params.set('floor_id', contextId.value);
        return params;
    };
    return { buildingId, blockId, buildings, blocks, floors, context, selectBuilding, selectBlock, initialize, query };
}
