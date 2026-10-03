@extends('management.layouts.app')

@section('title', 'اشتراک و مجوز ساختمان‌ها')
@section('page-title', 'اشتراک و مجوز ساختمان‌ها')
@section('page-subtitle', 'مدیریت چرخه فعال‌سازی، تمدید، دوره تنفس، تعلیق و لغو اشتراک')

@push('styles')
<style>
    .subscription-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px}
    .subscription-card{border:1px solid var(--border);border-radius:18px;background:var(--surface);padding:18px;box-shadow:0 12px 36px rgba(4,19,35,.04)}
    .subscription-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
    .subscription-card h3{margin:0;color:var(--text);font-size:16px}
    .subscription-card p{margin:5px 0 0;color:var(--muted);font-size:12px}
    .subscription-status{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;background:var(--surface-soft);font-size:11px;font-weight:800}
    .subscription-status.is-active{background:var(--success-soft);color:var(--success)}
    .subscription-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
    .subscription-form label{display:grid;gap:5px;color:var(--text-soft);font-size:11px}
    .subscription-form select,.subscription-form input{height:42px;border:1px solid var(--border);border-radius:11px;background:var(--surface-soft);color:var(--text);padding-inline:10px;font:inherit}
    .subscription-form .wide{grid-column:1/-1}
    .subscription-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
    .subscription-actions form{margin:0}
    .subscription-button{min-height:38px;padding:0 13px;border:1px solid var(--border);border-radius:10px;background:var(--surface-soft);color:var(--text);font:inherit;font-size:12px;font-weight:800;cursor:pointer}
    .subscription-button.primary{background:var(--primary-700);border-color:var(--primary-700);color:#fff}
    .subscription-button.danger{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 30%,var(--border));background:var(--danger-soft)}
    .subscription-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}
    .subscription-meta div{padding:10px;border-radius:11px;background:var(--surface-soft)}
    .subscription-meta span{display:block;color:var(--muted);font-size:10px}
    .subscription-meta strong{display:block;margin-top:3px;color:var(--text);font-size:12px}
    .subscription-alert{margin-bottom:14px;padding:11px 13px;border-radius:12px;background:var(--success-soft);color:var(--success);font-size:12px;font-weight:700}
    .subscription-errors{margin-bottom:14px;padding:11px 13px;border-radius:12px;background:var(--danger-soft);color:var(--danger);font-size:12px}
    @media(max-width:720px){.subscription-form,.subscription-meta{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
    @if (session('status'))
        <div class="subscription-alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="subscription-errors">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="subscription-grid">
        @forelse ($buildings as $building)
            @php
                $subscription = $building->buildingSubscriptions->first();
                $usable = $subscription?->isUsable() ?? false;
            @endphp

            <article class="subscription-card">
                <div class="subscription-card__head">
                    <div>
                        <h3>{{ $building->title }}</h3>
                        <p>{{ $building->code }} · {{ $building->complex?->title }}</p>
                    </div>

                    <span class="subscription-status {{ $usable ? 'is-active' : '' }}">
                        {{ $usable ? 'فعال' : ($subscription?->status?->label() ?? 'بدون اشتراک') }}
                    </span>
                </div>

                @if ($subscription)
                    <div class="subscription-meta">
                        <div>
                            <span>پلن</span>
                            <strong>{{ $subscription->plan?->title ?? '—' }}</strong>
                        </div>
                        <div>
                            <span>پایان اشتراک</span>
                            <strong>{{ $subscription->expires_at?->format('Y-m-d H:i') ?? 'نامحدود' }}</strong>
                        </div>
                        <div>
                            <span>پایان مهلت تنفس</span>
                            <strong>{{ $subscription->grace_ends_at?->format('Y-m-d H:i') ?? '—' }}</strong>
                        </div>
                        <div>
                            <span>آخرین تمدید</span>
                            <strong>{{ $subscription->renewed_at?->format('Y-m-d H:i') ?? '—' }}</strong>
                        </div>
                    </div>

                    <div class="subscription-actions">
                        <form method="POST" action="{{ route('management.subscriptions.renew', $subscription) }}">
                            @csrf
                            <input type="hidden" name="grace_days" value="7">
                            <button class="subscription-button" type="submit">تمدید طبق پلن</button>
                        </form>

                        <form method="POST" action="{{ route('management.subscriptions.suspend', $subscription) }}">
                            @csrf
                            <button class="subscription-button" type="submit">تعلیق</button>
                        </form>

                        <form method="POST" action="{{ route('management.subscriptions.cancel', $subscription) }}">
                            @csrf
                            <button class="subscription-button danger" type="submit">لغو</button>
                        </form>
                    </div>
                @endif

                <form
                    class="subscription-form"
                    method="POST"
                    action="{{ route('management.subscriptions.activate', $building) }}"
                >
                    @csrf

                    <label class="wide">
                        <span>فعال‌سازی / تغییر پلن</span>
                        <select name="plan_id" required>
                            <option value="">انتخاب پلن</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->title }}
                                    @if ($plan->duration_days)
                                        · {{ $plan->duration_days }} روز
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>مدت سفارشی (روز)</span>
                        <input type="number" name="duration_days" min="1" max="3660" placeholder="طبق پلن">
                    </label>

                    <label>
                        <span>مهلت تنفس (روز)</span>
                        <input type="number" name="grace_days" min="0" max="90" value="7">
                    </label>

                    <button class="subscription-button primary wide" type="submit">
                        فعال‌سازی پلن
                    </button>
                </form>
            </article>
        @empty
            <article class="subscription-card">
                <h3>ساختمانی در محدوده دسترسی شما وجود ندارد.</h3>
            </article>
        @endforelse
    </div>
@endsection
