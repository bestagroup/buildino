<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use App\Models\ChargePeriod;
use App\Models\ChargeSmsNotice;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ChargeSmsService
{
    public function authorize(User $actor, ChargePeriod $period): void
    {
        abort_unless(app(PermissionChecker::class)->allows($actor, 'charge-sms.manage', $period->building), 403);
        abort_unless(in_array($period->status->value, ['issued', 'closed'], true), 422, 'ابتدا صورتحساب‌های دوره صادر شود.');
    }

    public function invoices(ChargePeriod $period)
    {
        return UnitInvoice::where('charge_period_id', $period->id)->where('building_id', $period->building_id)
            ->whereNull('start_charge_period_id')->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->with('unit')->orderBy('id')->get();
    }

    public function recipients(ChargePeriod $period): array
    {
        $invoices = $this->invoices($period);
        $occupancies = \App\Models\UnitOccupancy::whereIn('unit_id', $invoices->pluck('unit_id'))
            ->whereDate('starts_at', '<=', $period->period_end)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $period->period_start))
            ->get()->groupBy('unit_id');
        $rows = [];
        $unresolved = [];
        foreach ($invoices as $invoice) {
            if ($invoice->total_amount === 0) {
                continue;
            }
            $links = $occupancies->get($invoice->unit_id, collect());
            $primary = $links->where('is_primary', true)->pluck('user_id')->unique();
            $candidates = $primary->isNotEmpty() ? $primary : $links->pluck('user_id')->unique();
            $id = $candidates->count() === 1 ? $candidates->first() : null;
            if (! $id) {
                $unresolved[$invoice->unit_id] = $invoice->unit;

                continue;
            }
            $rows[$id] ??= ['user_id' => (int) $id, 'amount' => 0, 'invoice_ids' => [], 'units' => []];
            $rows[$id]['amount'] += $invoice->total_amount < 0
                ? -(int) $invoice->credit_remaining_amount
                : (int) $invoice->total_amount - (int) $invoice->credit_applied_amount;
            $rows[$id]['invoice_ids'][] = $invoice->id;
            $rows[$id]['units'][$invoice->unit_id] = $invoice->unit->unit_number;
        }
        $rows = array_filter($rows, fn ($row) => $row['amount'] > 0);
        $factor = ($period->building->currency ?: 'IRR') === 'IRT' ? 10 : 1;
        foreach ($rows as &$amountRow) { $amountRow['amount'] *= $factor; }
        unset($amountRow);
        $users = User::whereIn('id', array_keys($rows))->get()->keyBy('id');
        $notices = ChargeSmsNotice::where('charge_period_id', $period->id)->get()->keyBy('user_id');
        foreach ($rows as $id => &$row) {
            $row['user'] = $users[$id] ?? null;
            $row['notice'] = $notices[$id] ?? null;
            $row['eligible'] = $row['user'] && $row['user']->is_active && ! $row['user']->is_blocked && preg_match('/^09[0-9]{9}$/D', $row['user']->mobile ?? '');
        }

        return ['rows' => $rows, 'unresolved' => $unresolved];
    }

    public function send(User $actor, ChargePeriod $period, int $userId): ChargeSmsNotice
    {
        $this->authorize($actor, $period);
        if (config('notifications.sms_provider') !== 'ghasedak' || ! trim((string) config('notifications.sms_templates.charge.notice.template'))) {
            throw ValidationException::withMessages(['sms' => 'قالب تأییدشده پیامک شارژ و سرویس قاصدک را ابتدا تنظیم کنید.']);
        }
        // Commit a durable claim BEFORE contacting the provider. Never replay an uncertain request.
        [$notice,$claimed] = DB::transaction(function () use ($actor, $period, $userId) {
            $period = ChargePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $period);
            if ($existing = ChargeSmsNotice::where('charge_period_id', $period->id)->where('user_id', $userId)->first()) {
                return [$existing, false];
            }
            $recipients = $this->recipients($period);
            if ($recipients['unresolved']) {
                throw ValidationException::withMessages(['recipient' => 'سوابق ساکن اصلی برخی واحدها در این دوره ناقص یا مبهم است؛ ابتدا سوابق سکونت را اصلاح کنید.']);
            }
            $row = $recipients['rows'][$userId] ?? null;
            abort_unless($row && $row['eligible'], 422, 'مخاطب یا مبلغ قابل ارسال معتبر نیست.');
            $currency = 'IRR';
            $message = new SmsTemplateMessage('charge.notice', [
                'param1' => trim($row['user']->first_name.' '.$row['user']->last_name),
                'param2' => \Morilog\Jalali\Jalalian::fromCarbon($period->period_start)->format('F Y'),
                'param3' => number_format($row['amount'], 0, '.', ',').' ریال',
            ]);
            $payload = app(SmsTemplateRegistry::class)->payload($message);
            $notice = ChargeSmsNotice::create(['building_id' => $period->building_id, 'charge_period_id' => $period->id, 'user_id' => $userId, 'created_by' => $actor->id, 'mobile' => $row['user']->mobile, 'amount' => $row['amount'], 'currency' => $currency, 'invoice_ids' => $row['invoice_ids'], 'parameters' => $message->parameters, 'template' => $payload['template'], 'status' => 'processing']);

            return [$notice, true];
        });
        if (! $claimed) {
            return $notice;
        }
        try {
            $result = app(SmsSender::class)->send($notice->mobile, new SmsTemplateMessage('charge.notice', $notice->parameters));
            if (($result['accepted'] ?? false) !== true) {
                throw new \RuntimeException('SMS acceptance not confirmed');
            }
            $notice->update(['status' => 'accepted', 'provider_message_id' => isset($result['provider_message_id']) ? (string) $result['provider_message_id'] : null, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $notice->update(['status' => 'unknown']);
            Log::warning('Charge SMS outcome requires provider review',['notice_id' => $notice->id, 'exception_class' => $e::class]);
        }

        return $notice->fresh();
    }
}
