import type { CrudField, CrudRecord } from '../types/management';

export const structure = {
    complexes: { singular: 'مجتمع', parent: '', child: 'buildings', context: '', childContext: 'complex_id' },
    buildings: { singular: 'ساختمان', parent: 'complexes', child: 'blocks', context: '', childContext: 'building_id' },
    blocks: { singular: 'بلوک', parent: 'buildings', child: 'floors', context: 'building_id', childContext: 'block_id' },
    floors: { singular: 'طبقه', parent: 'blocks', child: 'units', context: 'block_id', childContext: 'floor_id' },
    units: { singular: 'واحد', parent: 'floors', child: '', context: 'floor_id', childContext: '' },
} as const;
export type StructureKey = keyof typeof structure;
export interface Abilities { create: boolean; update: boolean; delete: boolean; childView?: boolean }
export interface StructureContext extends Abilities {
    id: number; label: string; title?: string; building_id: number; block_id: number | null; complex_id: number;
}
export interface StructureUi {
    contexts: StructureContext[];
    hierarchy?: {
        buildings: { id: number; title: string; complex_id: number }[];
        blocks: { id: number; title: string; building_id: number }[];
    };
    abilities: Record<string, Abilities>;
    globalAbilities: Abilities;
    parentAbilities: Record<string, Abilities>;
    canCreate: boolean;
    createParents: { value: number; label: string }[];
    canSetup: boolean;
    setupUrl: string;
    locations: { name: string; cities: { name: string; label: string }[] }[];
}
export const readValue = (record: CrudRecord, path: string): unknown => {
    const read = (key: string) => key.split('.').reduce<unknown>((value, part) => value && typeof value === 'object' ? (value as Record<string, unknown>)[part] : undefined, record);
    return read(path) ?? read('location.' + path) ?? read('statistics.' + path);
};
export const display = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'فعال' : 'غیرفعال';
    if (typeof value === 'object') { const row = value as CrudRecord; return String(row.title ?? row.label ?? row.id ?? '—'); }
    return String(value);
};
export const payloadFor = (fields: CrudField[], form: CrudRecord, mode: 'create' | 'edit'): CrudRecord => Object.fromEntries(
    fields.filter(field => !(mode === 'edit' && (field.create_only || field.readonly_on_edit)) && !(mode === 'create' && field.edit_only))
        .map(field => {
            const value = form[field.name];
            return [field.name, field.type === 'checkbox' ? Boolean(value) : value === '' || value === undefined || value === null ? null : field.type === 'number' ? Number(value) : value];
        }),
);
export const primaryFields = new Set(['complex_id', 'code', 'title', 'building_number', 'floor_number', 'unit_number', 'area', 'usage_type', 'bedrooms']);
