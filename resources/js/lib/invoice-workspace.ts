export type InvoiceResource = 'invoices' | 'invoice-installments' | 'payments';
export interface InvoiceItem { id?: number; title: string; quantity: number | string; unit_amount: number | string; description?: string | null; charge_item_id?: number | null; metadata?: unknown }
export interface Installment { id?: number; amount: number | string; due_date: string; installment_number?: number; paid_amount?: number; penalty_amount?: number; waived_amount?: number; status?: string; metadata?: unknown }
export interface Invoice {
    id: number; building_id: number; unit_id: number; invoice_number: string; status: string;
    issue_date: string; due_date: string; period_start?: string | null; period_end?: string | null;
    subtotal: number; discount_amount: number; penalty_amount: number; total_amount: number; paid_amount: number; outstanding_amount: number;
    description?: string | null; items?: InvoiceItem[]; installments?: Installment[];
    charge_period_id?: number | null; payer?: { name: string } | null; payer_responsibility?: string; unit?: { unit_number: string; title?: string };
}
export interface Payment { id: number; building_id: number; payment_number: string; amount: number; method: string; status: string; currency?: string; description?: string; paid_at?: string; allocations?: { payable_id: number; payable_type: string; amount: number }[] }
export interface InvoiceWorkspace {
    buildings: { id: number; title: string; currency: string; abilities: Record<string, boolean> }[];
    units: { id: number; building_id: number; label: string }[];
    navigation: { key: string; title: string; url: string }[];
    groups: { title: string; resources: string[] }[];
    prefix: string; actionPermissions: Record<string, string>;
    lookups: Record<string, { value: number; label: string; building_id: number | null; abilities?: Record<string, boolean> }[]>;
    blocks: { value: number; label: string; building_id: number; abilities: Record<string, boolean> }[];
    initial: { building_id?: string; unit_id?: string; unit_invoice_id?: string; charge_period_id?: string };
}
export const invoiceLabels: Record<string, string> = { draft: 'پیش‌نویس', issued: 'صادرشده', partial: 'بخشی پرداخت‌شده', paid: 'پرداخت‌شده', overdue: 'سررسید گذشته', cancelled: 'لغوشده', void: 'باطل‌شده', pending: 'در انتظار تأیید', processing: 'در حال پردازش', failed: 'ناموفق', refunded: 'بازپرداخت‌شده', partial_refund: 'بازپرداخت جزئی' };
export const paymentMethods: Record<string, string> = { manual: 'دستی', cash: 'نقدی', bank_transfer: 'انتقال بانکی', pos: 'کارت‌خوان', qr: 'کد QR', bill: 'قبض', installment: 'اقساط', online: 'آنلاین' };
export const money = (value: unknown) => Number(value ?? 0).toLocaleString('fa-IR');
export const day = (value?: string | null) => /^\d{4}-\d{2}-\d{2}/.test(value || '') ? new Date(value!.slice(0, 10) + 'T12:00:00').toLocaleDateString('fa-IR') : '—';
export const today = () => { const date = new Date(); return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-'); };
export const payable = (invoice: Invoice) => ['issued', 'partial', 'overdue'].includes(invoice.status) && invoice.outstanding_amount > 0;
export const canPlan = (invoice: Invoice) => ['issued', 'overdue'].includes(invoice.status) && invoice.paid_amount === 0;
export const itemTotal = (items: InvoiceItem[]) => items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_amount || 0), 0);
export const invoiceTotal = (items: InvoiceItem[], discount: unknown, penalty: unknown) => Math.max(0, itemTotal(items) - Number(discount || 0) + Number(penalty || 0));
export const splitInstallments = (total: number, count: number, firstDate: string): Installment[] => {
    if (!Number.isSafeInteger(total) || !Number.isInteger(count) || count < 2 || count > 60 || total < count || !/^\d{4}-\d{2}-\d{2}$/.test(firstDate)) return [];
    const first = new Date(firstDate + 'T12:00:00'), base = Math.floor(total / count);
    return Array.from({ length: count }, (_, index) => {
        const date = new Date(first); date.setDate(first.getDate() + index * 30);
        return { amount: base + (index === count - 1 ? total - base * count : 0), due_date: [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-') };
    });
};
