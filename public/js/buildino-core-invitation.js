(() => {
    'use strict';
    const key = 'buildino.pending-invitation';
    const card = document.querySelector('[data-pending-invitation]');
    if (!card) return;
    let pending;
    try { pending = JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return; }
    if (!/^[a-zA-Z0-9]{32,255}$/.test(pending?.token || '')) return;
    card.hidden = false;
    const status = card.querySelector('[role="status"]');
    const errorBox = card.querySelector('[role="alert"]');
    const details = card.querySelector('[data-invitation-details]');
    const accept = card.querySelector('[data-invitation-accept]');
    const cancel = card.querySelector('[data-invitation-dismiss]');
    const retry = card.querySelector('[data-invitation-retry]');
    const clear = () => { try { sessionStorage.removeItem(key); } catch {} };
    let busy = false;
    const setBusy = (value) => { busy = value; card.setAttribute('aria-busy', String(value)); accept.disabled = value; retry.disabled = value; cancel.disabled = value; };
    const fail = (error) => {
        status.textContent = '';
        errorBox.textContent = error.status === 404 ? 'دعوتی با این لینک پیدا نشد.'
            : error.status === 403 ? 'این دعوت برای حساب شما قابل پذیرش نیست.'
            : [410, 422].includes(error.status) ? 'این دعوت معتبر یا فعال نیست؛ از دعوت‌کننده لینک تازه دریافت کنید.'
            : error.status ? error.message : 'ارتباط برقرار نشد؛ دوباره تلاش کنید.';
        if ([403, 404, 410, 422].includes(error.status)) {
            clear(); accept.hidden = true; retry.hidden = true;
            const hint = document.createElement('p'); hint.textContent = 'برای بررسی دسترسی یا دریافت دعوت تازه، با دعوت‌کننده تماس بگیرید.'; details.append(hint);
        } else { retry.hidden = false; }
    };
    const resolve = async () => {
        if (busy) return;
        setBusy(true); errorBox.textContent = ''; status.textContent = 'در حال بررسی دعوت…'; accept.hidden = true; retry.hidden = true;
        try {
            const result = await window.BuildinoCoreFlows.request('/api/v1/unit-invitations/resolve', { method: 'POST', body: JSON.stringify({ token: pending.token }) });
            const invitation = result.data;
            if (!invitation || !['pending', 'sent', 'accepted'].includes(invitation.status)) {
                const error = new Error('این دعوت دیگر فعال نیست.'); error.status = 422; throw error;
            }
            const relations = { owner: 'مالک', tenant: 'مستأجر', resident: 'ساکن', family_member: 'عضو خانواده' };
            details.replaceChildren();
            const summary = document.createElement('p');
            summary.textContent = `دعوت برای واحد ${invitation.unit?.unit_number || invitation.unit_id} با نوع ارتباط «${relations[invitation.relation_type] || invitation.relation_type}».`;
            details.append(summary);
            const inviter = invitation.inviter;
            if (inviter) { const label = document.createElement('p'); label.textContent = `دعوت‌کننده: ${inviter.first_name || ''} ${inviter.last_name || ''}`; details.append(label); }
            if (invitation.message) { const message = document.createElement('p'); message.textContent = invitation.message; details.append(message); }
            if (invitation.relation_type === 'owner') {
                const note = document.createElement('p'); note.className = 'core-flow__hint'; note.textContent = 'پذیرش این دعوت دسترسی شما به واحد را برقرار می‌کند؛ ثبت مالکیت حقوقی مرحلهٔ مستقلی دارد.'; details.append(note);
            }
            if (invitation.status === 'accepted') { clear(); status.textContent = 'این دعوت قبلاً پذیرفته شده است.'; }
            else { status.textContent = 'برای اتصال به واحد، دعوت را تأیید کنید.'; accept.hidden = false; }
        } catch (error) { fail(error); } finally {
            setBusy(false);
            const heading = card.querySelector('h2'); heading.tabIndex = -1;
            heading.focus({ preventScroll: true });
            card.scrollIntoView({ block: 'start' });
        }
    };
    accept.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true); errorBox.textContent = ''; retry.hidden = true;
        try {
            await window.BuildinoCoreFlows.request('/api/v1/unit-invitations/accept', { method: 'POST', body: JSON.stringify({ token: pending.token }) });
            clear(); status.textContent = 'دعوت پذیرفته شد. در حال بازکردن اطلاعات واحد…';
            window.location.assign(card.dataset.residentUrl);
        } catch (error) { fail(error); setBusy(false); }
    });
    cancel.addEventListener('click', () => { if (!busy) { clear(); card.hidden = true; } });
    retry.addEventListener('click', resolve);
    void resolve();
})();
