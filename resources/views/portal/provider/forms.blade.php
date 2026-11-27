<div class="modal fade" id="providerQuoteModal" tabindex="-1" aria-labelledby="provider-quote-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content portal-modal" data-portal-form="provider-quote">
        <header class="modal-header"><h3 class="modal-title" id="provider-quote-title">پیشنهاد قیمت</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن پیشنهاد قیمت"></button></header>
        <div class="modal-body portal-form-grid">
            <input type="hidden" name="service_request_id">
            <div class="portal-topup-target portal-field--wide"><span>درخواست</span><strong data-provider-quote-title>—</strong></div>
            <label class="portal-field"><span>مبلغ پیشنهادی (<span data-provider-quote-currency>—</span>) <b aria-hidden="true">*</b></span><input type="text" inputmode="numeric" data-number-input name="amount" min="1" required></label>
            <label class="portal-field"><span>اعتبار پیشنهاد تا</span><input type="datetime-local" name="valid_until"></label>
            <label class="portal-field portal-field--wide"><span>توضیحات</span><textarea name="notes" rows="3" maxlength="5000"></textarea></label>
        </div><footer class="modal-footer"><button type="button" class="portal-action-button" data-bs-dismiss="modal">انصراف</button><button type="submit" class="portal-primary-button">ثبت پیشنهاد</button></footer>
    </form></div>
</div>
<div class="modal fade" id="bankAccountModal" tabindex="-1" aria-labelledby="provider-bank-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content portal-modal" data-portal-form="provider-bank">
        <header class="modal-header"><h3 class="modal-title" id="provider-bank-title">ثبت حساب بانکی</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن ثبت حساب بانکی"></button></header>
        <div class="modal-body portal-form-grid">
            <label class="portal-field"><span>نام بانک</span><input name="bank_name" autocomplete="organization"></label>
            <label class="portal-field"><span>نام صاحب حساب <b aria-hidden="true">*</b></span><input name="account_holder_name" required autocomplete="name"></label>
            <label class="portal-field portal-field--wide"><span>شماره شبا <b aria-hidden="true">*</b></span><input name="iban" dir="ltr" placeholder="IR…" required maxlength="26" autocapitalize="characters" aria-describedby="provider-iban-help"><small id="provider-iban-help">IR و ۲۴ رقم، بدون فاصله</small></label>
            <label class="portal-field"><span>شماره حساب</span><input name="account_number" dir="ltr" inputmode="numeric"></label>
            <label class="portal-field"><span>شماره کارت</span><input name="card_number" dir="ltr" inputmode="numeric" maxlength="16"></label>
            <label class="portal-check portal-field--wide"><input type="checkbox" name="is_default" value="1"><span>حساب پیش‌فرض من باشد</span></label>
        </div><footer class="modal-footer"><button type="button" class="portal-action-button" data-bs-dismiss="modal">انصراف</button><button type="submit" class="portal-primary-button">ثبت حساب</button></footer>
    </form></div>
</div>
<div class="modal fade" id="payoutModal" tabindex="-1" aria-labelledby="provider-payout-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content portal-modal" data-portal-form="provider-payout">
        <header class="modal-header"><h3 class="modal-title" id="provider-payout-title">درخواست تسویه</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن درخواست تسویه"></button></header>
        <div class="modal-body portal-form-grid">
            <div class="provider-form-balance portal-field--wide"><span>موجودی قابل برداشت</span><strong>{{ $money($portalData['personal_wallet']['available_balance']) }} ریال</strong></div>
            <label class="portal-field portal-field--wide"><span>حساب بانکی تأییدشده <b aria-hidden="true">*</b></span><select name="provider_bank_account_id" required><option value="">انتخاب حساب بانکی</option>@foreach($portalData['bank_accounts']->where('is_verified',true) as $account)<option value="{{ $account->id }}" @selected($account->is_default)>{{ $account->bank_name ?: 'حساب' }} · {{ $account->iban }}</option>@endforeach</select></label>
            <label class="portal-field portal-field--wide"><span>مبلغ تسویه (ریال) <b aria-hidden="true">*</b></span><input type="text" inputmode="numeric" data-number-input name="amount" min="1" max="{{ max(0,$portalData['personal_wallet']['available_balance']) }}" required></label>
        </div><footer class="modal-footer"><button type="button" class="portal-action-button" data-bs-dismiss="modal">انصراف</button><button type="submit" class="portal-primary-button">ثبت درخواست تسویه</button></footer>
    </form></div>
</div>
