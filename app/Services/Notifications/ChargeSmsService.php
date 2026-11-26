<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use App\Models\ChargePeriod;
use App\Models\ChargeSmsNotice;
use App\Models\UnitInvoice;
use App\Models\User;
use App\Services\Web\PortalAccessService;
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
        $mappings = DB::table('charge_sms_recipients')->where('charge_period_id', $period->id)->pluck('user_id', 'unit_id');
        $rows = [];
        $unresolved = [];
        $mappedUnits = [];
        foreach ($mappings->unique() as $mappedId) {
            $mappedUser = User::find($mappedId);
            $mappedUnits[$mappedId] = $mappedUser ? app(PortalAccessService::class)->residentUnits($mappedUser)->pluck('id')->all() : [];
        }
        foreach ($this->invoices($period) as $invoice) {
            if ($invoice->outstanding_amount <= 0) {
                continue;
            }
            $id = $invoice->payer_user_id ?: ($mappings[$invoice->unit_id] ?? null);
            if (! $invoice->payer_user_id && $id && ! in_array((int) $invoice->unit_id, $mappedUnits[$id] ?? [], true)) {
                $id = null;
            }
            if (! $id) {
                $unresolved[$invoice->unit_id] = $invoice->unit;

                continue;
            }
            $rows[$id] ??= ['user_id' => (int) $id, 'amount' => 0, 'invoice_ids' => [], 'units' => []];
            $rows[$id]['amount'] += (int) $invoice->outstanding_amount;
            $rows[$id]['invoice_ids'][] = $invoice->id;
            $rows[$id]['units'][$invoice->unit_id] = $invoice->unit->unit_number;
        }
        $users = User::whereIn('id', array_keys($rows))->get()->keyBy('id');
        $notices = ChargeSmsNotice::where('charge_period_id', $period->id)->get()->keyBy('user_id');
        foreach ($rows as $id => &$row) {
            $row['user'] = $users[$id] ?? null;
            $row['notice'] = $notices[$id] ?? null;
            $row['eligible'] = $row['user'] && $row['user']->is_active && ! $row['user']->is_blocked && preg_match('/^09[0-9]{9}$/D', $row['user']->mobile ?? '');
        }

        return ['rows' => $rows, 'unresolved' => $unresolved];
    }

    public function assign(User $actor, ChargePeriod $period, int $unitId, int $userId): void
    {
        $this->authorize($actor, $period);
        DB::transaction(function () use ($actor, $period, $unitId, $userId) {
            ChargePeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            if (ChargeSmsNotice::where('charge_period_id', $period->id)->exists()) {
                throw ValidationException::withMessages(['recipient' => 'تعیین مخاطبان باید پیش از اولین ارسال دوره تکمیل شود.']);
            }
            abort_unless($this->invoices($period)->contains('unit_id', $unitId), 404);
            $user = User::findOrFail($userId);
            abort_unless(app(PortalAccessService::class)->residentUnits($user)->contains('id', $unitId), 422);
            DB::table('charge_sms_recipients')->updateOrInsert(['charge_period_id' => $period->id, 'unit_id' => $unitId], ['user_id' => $userId, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        });
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
                throw ValidationException::withMessages(['recipient' => 'ابتدا مخاطب تمام واحدهای فاقد مسئول پرداخت را تعیین کنید.']);
            }
            $row = $recipients['rows'][$userId] ?? null;
            abort_unless($row && $row['eligible'], 422, 'مخاطب یا مبلغ قابل ارسال معتبر نیست.');
            $currency = $period->building->currency ?: 'IRR';
            $message = new SmsTemplateMessage('charge.notice', [
                'param1' => $period->building->title,
                'param2' => $period->title,
                'param3' => number_format($row['amount']).' '.($currency === 'IRT' ? 'تومان' : 'ریال'),
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
