import type { Person } from './service-requests';
export interface TicketMessage { id: number; user_id: number; message: string; is_internal: boolean; created_at?: string; user?: Person }
export interface SupportTicketRow {
    id: number; ticket_number: string; building_id: number | null; unit_id: number | null; user_id: number; support_category_id: number | null;
    subject: string; description: string; status: string; priority: string; assigned_to: number | null; assignee?: Person;
    support_category?: { id: number; title: string }; created_at?: string; response_due_at?: string; resolution_due_at?: string;
    first_response_at?: string; resolved_at?: string; closed_at?: string; resolution?: string;
}
export interface SupportAbilities { view: boolean; create: boolean; update: boolean; delete: boolean }
export interface SupportWorkspace {
    buildings: { id: number; title: string; abilities: SupportAbilities; canCreate: boolean }[];
    units: { id: number; building_id: number; label: string; canCreate: boolean; canUpdate: boolean }[];
    categories: { id: number; title: string; is_active: boolean }[]; userId: number; canCreate: boolean; canManageCategories: boolean;
    globalAbilities: SupportAbilities; initial: { building_id?: string; unit_id?: string };
}
export const ticketStatuses: Record<string, string> = { open: 'ثبت‌شده', assigned: 'ارجاع‌شده', in_progress: 'در حال رسیدگی', waiting_user: 'در انتظار پاسخ کاربر', resolved: 'حل‌شده', closed: 'بسته‌شده' };
export const ticketPriorities: Record<string, string> = { low: 'کم', medium: 'متوسط', high: 'بالا', urgent: 'فوری' };
export const ticketClosed = (ticket: SupportTicketRow) => ['resolved', 'closed'].includes(ticket.status);
export const normalizeTicket = (raw: Record<string, unknown>): SupportTicketRow => {
    const assignee = typeof raw.assigned_to === 'object' && raw.assigned_to ? raw.assigned_to as Person : undefined;
    return { ...raw, assigned_to: assignee?.id ?? raw.assigned_to ?? null, assignee } as SupportTicketRow;
};
export const ticketAbilities = (row: SupportTicketRow, workspace: SupportWorkspace): SupportAbilities => row.building_id === null ? workspace.globalAbilities : workspace.buildings.find(item => item.id === row.building_id)?.abilities || { view: false, create: false, update: false, delete: false };
export const ticketActions = (row: SupportTicketRow, workspace: SupportWorkspace): string[] => {
    const scope = ticketAbilities(row, workspace), closed = ticketClosed(row);
    return [!closed && scope.update && 'edit', !closed && scope.update && 'assign', !closed && scope.update && ['open', 'assigned', 'waiting_user'].includes(row.status) && 'start', !closed && scope.update && ['assigned', 'in_progress'].includes(row.status) && 'wait-user', !closed && scope.update && 'resolve', row.status === 'resolved' && scope.update && 'close', closed && (scope.update || row.user_id === workspace.userId) && 'reopen', scope.delete && 'delete'].filter(Boolean) as string[];
};
export const ticketDateTime = (value?: string | null) => value && !Number.isNaN(new Date(value).getTime()) ? new Date(value).toLocaleString('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }) : 'تعیین نشده';
