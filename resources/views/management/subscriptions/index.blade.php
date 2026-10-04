@extends('management.layouts.app')

@section('title', 'اشتراک و مجوز ساختمان‌ها')
@section('page-title', 'اشتراک و مجوز ساختمان‌ها')
@section('page-subtitle', 'مدیریت چرخه فعال‌سازی، تمدید، دوره تنفس، تعلیق و لغو اشتراک')

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
                        <form method="POST" action="{{ route('management.subscriptions.renew', $subscription) }}" data-buildino-submit>
                            @csrf
                            <input type="hidden" name="grace_days" value="7">
                            <button class="subscription-button" type="submit">تمدید طبق پلن</button>
                        </form>

                        <form method="POST" action="{{ route('management.subscriptions.suspend', $subscription) }}" data-buildino-submit>
                            @csrf
                            <button class="subscription-button" type="submit">تعلیق</button>
                        </form>

                        <form method="POST" action="{{ route('management.subscriptions.cancel', $subscription) }}" data-buildino-submit>
                            @csrf
                            <button class="subscription-button danger" type="submit">لغو</button>
                        </form>
                    </div>
                @endif

                <form
                    class="subscription-form"
                    method="POST"
                    action="{{ route('management.subscriptions.activate', $building) }}"
                    data-buildino-submit
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
