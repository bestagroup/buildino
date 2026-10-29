export interface ServiceQuote { id: number; provider_user_id: number; amount: number; commission_amount: number; provider_amount: number; status: string; notes?: string; valid_until?: string | null }
export interface ServiceRequestRow {
    id: number; building_id: number; unit_id: number | null; request_number: string; requested_by: number; assigned_to: number | null;
    type: string; title: string; description?: string; priority: string; status: string; created_at?: string; assigned_at?: string; completed_at?: string;
    requester?: Person; provider?: Person;
    quotes?: ServiceQuote[]; wallet_payment?: { status: string; payer_source: string; amount: number; provider_amount: number; commission_amount: number } | null;
}
export interface Person { id: number; first_name?: string; last_name?: string; mobile?: string }
export interface ServiceBuilding { id: number; title: string; currency: string; abilities: Record<string, boolean>; canCreate: boolean; finance: { active: boolean; user_wallet: boolean; unit_wallet: boolean } }
export interface ServiceUnit { id: number; building_id: number; label: string; canCreate: boolean; canUpdate: boolean; canUseWallet: boolean }
export interface ServiceWorkspace { buildings: ServiceBuilding[]; units: ServiceUnit[]; userId: number; initial: { building_id?: string; unit_id?: string } }
export const serviceStatuses: Record<string, string> = { open: 'ثبت‌شده', assigned: 'تخصیص‌یافته', in_progress: 'در حال انجام', awaiting_confirmation: 'در انتظار تأیید', completed: 'تکمیل‌شده', cancelled: 'لغوشده' };
export const servicePriorities: Record<string, string> = { low: 'کم', normal: 'عادی', high: 'بالا', urgent: 'فوری' };
export const quoteStatuses: Record<string, string> = { pending: 'در انتظار پذیرش', accepted: 'پذیرفته‌شده', rejected: 'ردشده', expired: 'منقضی‌شده', cancelled: 'لغوشده' };
export const personName = (person?: Person | null) => person ? [person.first_name, person.last_name].filter(Boolean).join(' ') || person.mobile || 'کاربر' : 'تعیین نشده';
// Loaded Eloquent relations share their snake_case names with the foreign keys.
export const normalizeRequest = (raw: Record<string, unknown>): ServiceRequestRow => {
    const requester = typeof raw.requested_by === 'object' && raw.requested_by ? raw.requested_by as Person : undefined;
    const provider = typeof raw.assigned_to === 'object' && raw.assigned_to ? raw.assigned_to as Person : undefined;
    return { ...raw, requested_by: requester?.id ?? raw.requested_by, assigned_to: provider?.id ?? raw.assigned_to ?? null, requester, provider } as ServiceRequestRow;
};
export const editable = (row: ServiceRequestRow) => ['open', 'assigned'].includes(row.status);
export const activePayment = (row: ServiceRequestRow) => ['locked', 'settled'].includes(row.wallet_payment?.status || '');
export const closedRequest = (row: ServiceRequestRow) => ['completed', 'cancelled'].includes(row.status);
export function serviceActions(row: ServiceRequestRow, building: ServiceBuilding | undefined, userId: number): string[] {
    const permissions = building?.abilities || {}, own = row.requested_by === userId, provider = row.assigned_to === userId;
    const update = permissions['service-requests.update'] || own && row.status === 'open' && !row.assigned_to;
    const manage = provider || permissions['service-finance.manage'], locked = row.wallet_payment?.status === 'locked';
    return [
        editable(row) && !activePayment(row) && update && 'edit',
        editable(row) && update && !activePayment(row) && 'assign',
        !closedRequest(row) && row.assigned_to && !activePayment(row) && building?.finance.active && (provider || permissions['service-finance.quote']) && 'quote',
        locked && ['open', 'assigned'].includes(row.status) && manage && 'start',
        locked && row.status === 'in_progress' && manage && 'finish',
        locked && row.status === 'awaiting_confirmation' && (own || permissions['service-finance.settle']) && 'confirm',
        !closedRequest(row) && (own || permissions['service-finance.manage']) && 'cancel-financial',
        row.status === 'open' && !row.assigned_to && !activePayment(row) && (own || permissions['service-requests.delete']) && 'delete',
    ].filter(Boolean) as string[];
}
