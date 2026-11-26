<section class="nav-group is-open" data-nav-group="services-preview">
    <button type="button" class="nav-group__title" data-nav-toggle>
        <span>خدمات</span>
        @include('management.partials.icon', ['name' => 'chevron', 'size' => 14])
    </button>
    <div class="nav-group__items">
        @foreach ([
            'خدمات مالی و اعتباری',
            'خدمات تردد و امنیت',
            'خدمات رزرواسیون مشاعات',
            'خدمات ارتباط و اطلاع رسانی',
            'خدمات تاسیسات و تعمیرات',
            'خدمات خودرو در محل',
            'خدمات بیمه ای',
            'خدمات وندوری',
            'سرمایه گذاری در ساخت',
            'باشگاه مشتریان',
        ] as $serviceTitle)
            <div class="nav-link" aria-disabled="true">
                <span class="nav-link__label">{{ $serviceTitle }}</span>
            </div>
        @endforeach
    </div>
</section>
