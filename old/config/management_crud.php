<?php

return [
    'groups' => [
        'structure' => [
            'title' => 'ساختار ساختمان',
            'description' => 'مجتمع، ساختمان، بلوک، طبقه و واحد',
            'icon' => 'building'
        ],
        'access' => [
            'title' => 'کاربران و دسترسی',
            'description' => 'کاربر، نقش، Scope، مالکیت و سکونت',
            'icon' => 'users'
        ],
        'guest' => [
            'title' => 'مهمان و تردد',
            'description' => 'ثبت مهمان و رویداد ورود/خروج',
            'icon' => 'users'
        ],
        'facility' => [
            'title' => 'امکانات و رزرو',
            'description' => 'Facility، برنامه زمانی، Rule و Reservation',
            'icon' => 'calendar'
        ],
        'finance' => [
            'title' => 'مالی و کیف پول',
            'description' => 'شارژ، صورتحساب، پرداخت، بانک و تسویه',
            'icon' => 'wallet'
        ],
        'services' => [
            'title' => 'خدمات',
            'description' => 'درخواست خدمت و Workflow مالی',
            'icon' => 'tools'
        ],
        'support' => [
            'title' => 'پشتیبانی',
            'description' => 'تیکت، دسته‌بندی، SLA و پیام',
            'icon' => 'support'
        ],
        'content' => [
            'title' => 'اطلاع‌رسانی و اسناد',
            'description' => 'اعلان، اسناد، صورتجلسه و Notification',
            'icon' => 'bell'
        ],
        'reports' => [
            'title' => 'گزارش و کنترل',
            'description' => 'Export، گزارش و کنترل عملیاتی',
            'icon' => 'chart'
        ]
    ],
    'resources' => [
        'complexes' => [
            'group' => 'structure',
            'title' => 'مجتمع‌ها',
            'description' => 'تعریف و مدیریت مجتمع‌های سامانه',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/complexes?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/complexes'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/complexes/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/complexes/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'code',
                    'label' => 'کد'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'province',
                    'label' => 'استان'
                ],
                [
                    'key' => 'city',
                    'label' => 'شهر'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'code',
                    'label' => 'کد مجتمع',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان مجتمع',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'province',
                    'label' => 'استان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'city',
                    'label' => 'شهر',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'address',
                    'label' => 'آدرس',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'postal_code',
                    'label' => 'کدپستی',
                    'type' => 'text'
                ],
                [
                    'name' => 'latitude',
                    'label' => 'عرض جغرافیایی',
                    'type' => 'number',
                    'step' => 'any'
                ],
                [
                    'name' => 'longitude',
                    'label' => 'طول جغرافیایی',
                    'type' => 'number',
                    'step' => 'any'
                ],
                [
                    'name' => 'sort_order',
                    'label' => 'ترتیب',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'buildings' => [
            'group' => 'structure',
            'title' => 'ساختمان‌ها',
            'description' => 'ثبت ساختمان و اتصال آن به مجتمع',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/buildings/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/buildings/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'code',
                    'label' => 'کد'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'complex.title',
                    'label' => 'مجتمع'
                ],
                [
                    'key' => 'currency',
                    'label' => 'ارز'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'complex_id',
                    'label' => 'مجتمع',
                    'type' => 'select',
                    'lookup' => 'complexes',
                    'required' => true
                ],
                [
                    'name' => 'code',
                    'label' => 'کد ساختمان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان ساختمان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'building_number',
                    'label' => 'شماره ساختمان',
                    'type' => 'text'
                ],
                [
                    'name' => 'address',
                    'label' => 'آدرس',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'postal_code',
                    'label' => 'کدپستی',
                    'type' => 'text'
                ],
                [
                    'name' => 'latitude',
                    'label' => 'عرض جغرافیایی',
                    'type' => 'number',
                    'step' => 'any'
                ],
                [
                    'name' => 'longitude',
                    'label' => 'طول جغرافیایی',
                    'type' => 'number',
                    'step' => 'any'
                ],
                [
                    'name' => 'timezone',
                    'label' => 'Timezone',
                    'type' => 'text',
                    'default' => 'Asia/Tehran'
                ],
                [
                    'name' => 'currency',
                    'label' => 'Currency',
                    'type' => 'text',
                    'default' => 'IRR'
                ],
                [
                    'name' => 'floors_count',
                    'label' => 'تعداد طبقات',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'units_count',
                    'label' => 'تعداد واحدها',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'parking_count',
                    'label' => 'تعداد پارکینگ',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'storage_count',
                    'label' => 'تعداد انباری',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'construction_year',
                    'label' => 'سال ساخت',
                    'type' => 'number'
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'blocks' => [
            'group' => 'structure',
            'title' => 'بلوک‌ها',
            'description' => 'مدیریت بلوک‌های هر ساختمان',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/blocks?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/blocks'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/blocks/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/blocks/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'floors_count',
                    'label' => 'تعداد طبقات'
                ],
                [
                    'key' => 'sort_order',
                    'label' => 'ترتیب'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان بلوک',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'sort_order',
                    'label' => 'ترتیب',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'floors' => [
            'group' => 'structure',
            'title' => 'طبقات',
            'description' => 'مدیریت طبقات بلوک',
            'context' => [
                [
                    'name' => 'block_id',
                    'label' => 'بلوک',
                    'lookup' => 'blocks',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/blocks/{block_id}/floors?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/blocks/{block_id}/floors'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/floors/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/floors/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'floor_number',
                    'label' => 'شماره طبقه'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'units_count',
                    'label' => 'تعداد واحد'
                ],
                [
                    'key' => 'sort_order',
                    'label' => 'ترتیب'
                ]
            ],
            'fields' => [
                [
                    'name' => 'floor_number',
                    'label' => 'شماره طبقه',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text'
                ],
                [
                    'name' => 'sort_order',
                    'label' => 'ترتیب',
                    'type' => 'number',
                    'default' => 0
                ]
            ]
        ],
        'units' => [
            'group' => 'structure',
            'title' => 'واحدها',
            'description' => 'مدیریت واحدهای هر طبقه',
            'context' => [
                [
                    'name' => 'floor_id',
                    'label' => 'طبقه',
                    'lookup' => 'floors',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/floors/{floor_id}/units?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/floors/{floor_id}/units'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/units/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/units/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'unit_number',
                    'label' => 'شماره واحد'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'area',
                    'label' => 'متراژ'
                ],
                [
                    'key' => 'usage_type',
                    'label' => 'نوع کاربری'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'unit_number',
                    'label' => 'شماره واحد',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text'
                ],
                [
                    'name' => 'area',
                    'label' => 'متراژ',
                    'type' => 'number',
                    'step' => '0.01'
                ],
                [
                    'name' => 'bedrooms',
                    'label' => 'تعداد اتاق',
                    'type' => 'number'
                ],
                [
                    'name' => 'usage_type',
                    'label' => 'نوع کاربری',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'residential',
                            'label' => 'مسکونی'
                        ],
                        [
                            'value' => 'commercial',
                            'label' => 'تجاری'
                        ],
                        [
                            'value' => 'office',
                            'label' => 'اداری'
                        ],
                        [
                            'value' => 'other',
                            'label' => 'سایر'
                        ]
                    ]
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'users' => [
            'group' => 'access',
            'title' => 'کاربران',
            'description' => 'ایجاد و مدیریت کاربران سامانه',
            'list' => [
                'method' => 'GET',
                'url' => '/management/data/users?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/management/data/users'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/management/data/users/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/management/data/users/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'full_name',
                    'label' => 'نام'
                ],
                [
                    'key' => 'mobile',
                    'label' => 'موبایل'
                ],
                [
                    'key' => 'email',
                    'label' => 'ایمیل'
                ],
                [
                    'key' => 'roles',
                    'label' => 'نقش‌ها'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ],
                [
                    'key' => 'is_blocked',
                    'label' => 'مسدود'
                ]
            ],
            'fields' => [
                [
                    'name' => 'first_name',
                    'label' => 'نام',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'last_name',
                    'label' => 'نام خانوادگی',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'national_code',
                    'label' => 'کد ملی',
                    'type' => 'text'
                ],
                [
                    'name' => 'mobile',
                    'label' => 'موبایل',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'email',
                    'label' => 'ایمیل',
                    'type' => 'email'
                ],
                [
                    'name' => 'password',
                    'label' => 'رمز عبور',
                    'type' => 'password',
                    'required_on_create' => true
                ],
                [
                    'name' => 'verify_mobile',
                    'label' => 'تأیید موبایل',
                    'type' => 'checkbox',
                    'default' => true
                ],
                [
                    'name' => 'verify_email',
                    'label' => 'تأیید ایمیل',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ],
                [
                    'name' => 'is_blocked',
                    'label' => 'مسدود',
                    'type' => 'checkbox',
                    'default' => false
                ]
            ]
        ],
        'roles' => [
            'group' => 'access',
            'title' => 'نقش‌ها و مجوزها',
            'description' => 'تعریف Role و تخصیص Permission',
            'list' => [
                'method' => 'GET',
                'url' => '/management/data/roles'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/management/data/roles'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/management/data/roles/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/management/data/roles/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'name',
                    'label' => 'نام سیستمی'
                ],
                [
                    'key' => 'display_name',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'permissions_count',
                    'label' => 'مجوزها'
                ],
                [
                    'key' => 'assignments_count',
                    'label' => 'تخصیص‌ها'
                ],
                [
                    'key' => 'is_system',
                    'label' => 'سیستمی'
                ]
            ],
            'fields' => [
                [
                    'name' => 'name',
                    'label' => 'نام سیستمی',
                    'type' => 'text',
                    'required_on_create' => true,
                    'readonly_on_edit' => true
                ],
                [
                    'name' => 'display_name',
                    'label' => 'عنوان نقش',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'is_system',
                    'label' => 'نقش سیستمی',
                    'type' => 'checkbox',
                    'create_only' => true,
                    'default' => false
                ],
                [
                    'name' => 'permission_ids',
                    'label' => 'مجوزها',
                    'type' => 'multiselect',
                    'lookup' => 'permissions'
                ]
            ]
        ],
        'role-assignments' => [
            'group' => 'access',
            'title' => 'تخصیص نقش و Scope',
            'description' => 'اتصال Role به کاربر در سطح Global، مجتمع یا ساختمان',
            'list' => [
                'method' => 'GET',
                'url' => '/management/data/role-assignments'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/management/data/role-assignments'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/management/data/role-assignments/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/management/data/role-assignments/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'user_name',
                    'label' => 'کاربر'
                ],
                [
                    'key' => 'role_name',
                    'label' => 'نقش'
                ],
                [
                    'key' => 'scope_type',
                    'label' => 'نوع Scope'
                ],
                [
                    'key' => 'scope_id',
                    'label' => 'Scope ID'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'user_id',
                    'label' => 'کاربر',
                    'type' => 'select',
                    'lookup' => 'users',
                    'required' => true
                ],
                [
                    'name' => 'role_id',
                    'label' => 'نقش',
                    'type' => 'select',
                    'lookup' => 'roles',
                    'required' => true
                ],
                [
                    'name' => 'scope_type',
                    'label' => 'نوع Scope',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'global',
                            'label' => 'سراسری'
                        ],
                        [
                            'value' => 'complex',
                            'label' => 'مجتمع'
                        ],
                        [
                            'value' => 'building',
                            'label' => 'ساختمان'
                        ]
                    ]
                ],
                [
                    'name' => 'scope_id',
                    'label' => 'شناسه Scope',
                    'type' => 'number',
                    'help' => 'برای Global خالی بماند؛ برای مجتمع/ساختمان ID وارد شود.'
                ],
                [
                    'name' => 'starts_at',
                    'label' => 'شروع اعتبار',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'ends_at',
                    'label' => 'پایان اعتبار',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'ownerships' => [
            'group' => 'access',
            'title' => 'مالکیت واحد',
            'description' => 'ثبت و پایان مالکیت کاربران روی واحد',
            'context' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'lookup' => 'units',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/units/{unit_id}/ownerships'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/units/{unit_id}/ownerships'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/unit-ownerships/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'user.id',
                    'label' => 'User ID'
                ],
                [
                    'key' => 'ownership_percentage',
                    'label' => 'درصد مالکیت'
                ],
                [
                    'key' => 'starts_at',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'ends_at',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'is_primary',
                    'label' => 'اصلی'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'user_id',
                    'label' => 'مالک',
                    'type' => 'select',
                    'lookup' => 'users',
                    'required' => true
                ],
                [
                    'name' => 'ownership_percentage',
                    'label' => 'درصد مالکیت',
                    'type' => 'number',
                    'step' => '0.01'
                ],
                [
                    'name' => 'starts_at',
                    'label' => 'شروع',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'ends_at',
                    'label' => 'پایان',
                    'type' => 'date'
                ],
                [
                    'name' => 'is_primary',
                    'label' => 'مالک اصلی',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ],
                [
                    'name' => 'notes',
                    'label' => 'یادداشت',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'end',
                    'title' => 'پایان مالکیت',
                    'method' => 'POST',
                    'url' => '/api/v1/unit-ownerships/{id}/end',
                    'tone' => 'warning',
                    'fields' => [
                        [
                            'name' => 'ends_at',
                            'label' => 'تاریخ پایان',
                            'type' => 'date'
                        ]
                    ]
                ]
            ]
        ],
        'occupancies' => [
            'group' => 'access',
            'title' => 'سکونت واحد',
            'description' => 'ثبت ساکن، مستأجر یا اعضای خانواده',
            'context' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'lookup' => 'units',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/units/{unit_id}/occupancies'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/units/{unit_id}/occupancies'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/unit-occupancies/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'user.id',
                    'label' => 'User ID'
                ],
                [
                    'key' => 'occupancy_type',
                    'label' => 'نوع'
                ],
                [
                    'key' => 'starts_at',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'ends_at',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'is_primary',
                    'label' => 'اصلی'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'user_id',
                    'label' => 'ساکن',
                    'type' => 'select',
                    'lookup' => 'users',
                    'required' => true
                ],
                [
                    'name' => 'occupancy_type',
                    'label' => 'نوع سکونت',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'owner',
                            'label' => 'مالک ساکن'
                        ],
                        [
                            'value' => 'tenant',
                            'label' => 'مستأجر'
                        ],
                        [
                            'value' => 'resident',
                            'label' => 'ساکن'
                        ],
                        [
                            'value' => 'family_member',
                            'label' => 'عضو خانواده'
                        ]
                    ]
                ],
                [
                    'name' => 'starts_at',
                    'label' => 'شروع',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'ends_at',
                    'label' => 'پایان',
                    'type' => 'date'
                ],
                [
                    'name' => 'is_primary',
                    'label' => 'ساکن اصلی',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ],
                [
                    'name' => 'notes',
                    'label' => 'یادداشت',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'end',
                    'title' => 'پایان سکونت',
                    'method' => 'POST',
                    'url' => '/api/v1/unit-occupancies/{id}/end',
                    'tone' => 'warning',
                    'fields' => [
                        [
                            'name' => 'ends_at',
                            'label' => 'تاریخ پایان',
                            'type' => 'date'
                        ]
                    ]
                ]
            ]
        ],
        'invitations' => [
            'group' => 'access',
            'title' => 'دعوت کاربران',
            'description' => 'دعوت کاربر برای اتصال به واحد',
            'context' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'lookup' => 'units',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/units/{unit_id}/invitations'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/units/{unit_id}/invitations'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'relation_type',
                    'label' => 'نوع ارتباط'
                ],
                [
                    'key' => 'channel',
                    'label' => 'کانال'
                ],
                [
                    'key' => 'mobile',
                    'label' => 'موبایل'
                ],
                [
                    'key' => 'email',
                    'label' => 'ایمیل'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'expires_at',
                    'label' => 'انقضا'
                ]
            ],
            'fields' => [
                [
                    'name' => 'relation_type',
                    'label' => 'نوع ارتباط',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'owner',
                            'label' => 'مالک'
                        ],
                        [
                            'value' => 'tenant',
                            'label' => 'مستأجر'
                        ],
                        [
                            'value' => 'resident',
                            'label' => 'ساکن'
                        ],
                        [
                            'value' => 'family_member',
                            'label' => 'عضو خانواده'
                        ]
                    ]
                ],
                [
                    'name' => 'channel',
                    'label' => 'کانال دعوت',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'sms',
                            'label' => 'پیامک'
                        ],
                        [
                            'value' => 'email',
                            'label' => 'ایمیل'
                        ]
                    ]
                ],
                [
                    'name' => 'mobile',
                    'label' => 'موبایل',
                    'type' => 'text'
                ],
                [
                    'name' => 'email',
                    'label' => 'ایمیل',
                    'type' => 'email'
                ],
                [
                    'name' => 'expires_in_hours',
                    'label' => 'اعتبار (ساعت)',
                    'type' => 'number',
                    'default' => 72
                ]
            ],
            'actions' => [
                [
                    'key' => 'resend',
                    'title' => 'ارسال مجدد',
                    'method' => 'POST',
                    'url' => '/api/v1/unit-invitations/{id}/resend',
                    'tone' => 'primary'
                ],
                [
                    'key' => 'cancel',
                    'title' => 'لغو دعوت',
                    'method' => 'POST',
                    'url' => '/api/v1/unit-invitations/{id}/cancel',
                    'tone' => 'danger',
                    'confirm' => 'دعوت لغو شود؟'
                ]
            ]
        ],
        'guest-visits' => [
            'group' => 'guest',
            'title' => 'مهمان‌ها و بازدید',
            'description' => 'ثبت Visit و کنترل ورود و خروج',
            'context' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'lookup' => 'units',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/units/{unit_id}/guest-visits'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/units/{unit_id}/guest-visits'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/guest-visits/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'guest.first_name',
                    'label' => 'نام مهمان'
                ],
                [
                    'key' => 'guest.last_name',
                    'label' => 'نام خانوادگی'
                ],
                [
                    'key' => 'expected_entry_at',
                    'label' => 'ورود مورد انتظار'
                ],
                [
                    'key' => 'expected_exit_at',
                    'label' => 'خروج مورد انتظار'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'guest.first_name',
                    'label' => 'نام مهمان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'guest.last_name',
                    'label' => 'نام خانوادگی',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'guest.mobile',
                    'label' => 'موبایل',
                    'type' => 'text'
                ],
                [
                    'name' => 'guest.national_code',
                    'label' => 'کد ملی',
                    'type' => 'text'
                ],
                [
                    'name' => 'guest.vehicle_plate',
                    'label' => 'پلاک خودرو',
                    'type' => 'text'
                ],
                [
                    'name' => 'expected_entry_at',
                    'label' => 'زمان ورود',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'expected_exit_at',
                    'label' => 'زمان خروج',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'entry',
                    'title' => 'ثبت ورود',
                    'method' => 'POST',
                    'url' => '/api/v1/guest-visits/{id}/entry',
                    'tone' => 'success',
                    'fields' => [
                        [
                            'name' => 'gate',
                            'label' => 'گیت',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'entry_method',
                            'label' => 'روش ورود',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'vehicle_plate',
                            'label' => 'پلاک',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'یادداشت',
                            'type' => 'textarea'
                        ]
                    ]
                ],
                [
                    'key' => 'exit',
                    'title' => 'ثبت خروج',
                    'method' => 'POST',
                    'url' => '/api/v1/guest-visits/{id}/exit',
                    'tone' => 'warning',
                    'fields' => [
                        [
                            'name' => 'gate',
                            'label' => 'گیت',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'entry_method',
                            'label' => 'روش خروج',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'vehicle_plate',
                            'label' => 'پلاک',
                            'type' => 'text'
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'یادداشت',
                            'type' => 'textarea'
                        ]
                    ]
                ],
                [
                    'key' => 'cancel',
                    'title' => 'لغو Visit',
                    'method' => 'POST',
                    'url' => '/api/v1/guest-visits/{id}/cancel',
                    'tone' => 'danger',
                    'confirm' => 'بازدید لغو شود؟'
                ]
            ]
        ],
        'facilities' => [
            'group' => 'facility',
            'title' => 'امکانات مشاع',
            'description' => 'تعریف سالن، استخر، باشگاه و سایر Facilities',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/facilities'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/facilities'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/facilities/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/facilities/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'code',
                    'label' => 'کد'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'type',
                    'label' => 'نوع'
                ],
                [
                    'key' => 'capacity',
                    'label' => 'ظرفیت'
                ],
                [
                    'key' => 'default_price',
                    'label' => 'قیمت'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'code',
                    'label' => 'کد',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'image',
                    'label' => 'آدرس تصویر',
                    'type' => 'text'
                ],
                [
                    'name' => 'type',
                    'label' => 'نوع',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'gym',
                            'label' => 'باشگاه'
                        ],
                        [
                            'value' => 'pool',
                            'label' => 'استخر'
                        ],
                        [
                            'value' => 'roof_garden',
                            'label' => 'روف‌گاردن'
                        ],
                        [
                            'value' => 'meeting_hall',
                            'label' => 'سالن'
                        ],
                        [
                            'value' => 'other',
                            'label' => 'سایر'
                        ]
                    ]
                ],
                [
                    'name' => 'capacity',
                    'label' => 'ظرفیت',
                    'type' => 'number'
                ],
                [
                    'name' => 'default_price',
                    'label' => 'قیمت پیش‌فرض',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'requires_payment',
                    'label' => 'نیازمند پرداخت',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'requires_approval',
                    'label' => 'نیازمند تأیید',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'facility-schedules' => [
            'group' => 'facility',
            'title' => 'برنامه زمانی Facility',
            'description' => 'روزها و ساعات فعال هر Facility',
            'context' => [
                [
                    'name' => 'building_facility_id',
                    'label' => 'Facility',
                    'lookup' => 'facilities',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'day_of_week',
                    'label' => 'روز هفته'
                ],
                [
                    'key' => 'start_time',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'end_time',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'day_of_week',
                    'label' => 'روز هفته',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 0,
                            'label' => 'یکشنبه'
                        ],
                        [
                            'value' => 1,
                            'label' => 'دوشنبه'
                        ],
                        [
                            'value' => 2,
                            'label' => 'سه‌شنبه'
                        ],
                        [
                            'value' => 3,
                            'label' => 'چهارشنبه'
                        ],
                        [
                            'value' => 4,
                            'label' => 'پنجشنبه'
                        ],
                        [
                            'value' => 5,
                            'label' => 'جمعه'
                        ],
                        [
                            'value' => 6,
                            'label' => 'شنبه'
                        ]
                    ]
                ],
                [
                    'name' => 'start_time',
                    'label' => 'ساعت شروع',
                    'type' => 'time',
                    'required' => true
                ],
                [
                    'name' => 'end_time',
                    'label' => 'ساعت پایان',
                    'type' => 'time',
                    'required' => true
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'facility-time-slots' => [
            'group' => 'facility',
            'title' => 'بازه‌های زمانی Facility',
            'description' => 'تعریف Slotهای قابل رزرو',
        'list_transform' => 'schedule_time_slots',
            'context' => [
                [
                    'name' => 'building_facility_id',
                    'label' => 'Facility',
                    'lookup' => 'facilities',
                    'required' => true
                ],
                [
                    'name' => 'facility_schedule_id',
                    'label' => 'Schedule',
                    'lookup' => 'facility_schedules',
                    'required' => true,
                    'depends_on' => 'building_facility_id'
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules/{facility_schedule_id}/time-slots'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules/{facility_schedule_id}/time-slots/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/facilities/{building_facility_id}/schedules/{facility_schedule_id}/time-slots/{id}'
            ],
            'columns' => [
            [
                'key' => 'id',
                'label' => 'ID'
            ],
            [
                'key' => 'facility_schedule_id',
                'label' => 'Schedule'
            ],
            [
                'key' => 'start_time',
                'label' => 'شروع'
            ],
            [
                'key' => 'end_time',
                'label' => 'پایان'
            ],
            [
                'key' => 'capacity',
                'label' => 'ظرفیت'
            ],
            [
                'key' => 'price',
                'label' => 'قیمت'
            ],
            [
                'key' => 'is_active',
                'label' => 'فعال'
            ]
        ],
        'fields' => [
                [
                    'name' => 'start_time',
                    'label' => 'شروع Slot',
                    'type' => 'time',
                    'required' => true
                ],
                [
                    'name' => 'end_time',
                    'label' => 'پایان Slot',
                    'type' => 'time',
                    'required' => true
                ],
                [
                    'name' => 'capacity',
                    'label' => 'ظرفیت',
                    'type' => 'number'
                ],
                [
                    'name' => 'price',
                    'label' => 'قیمت',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ],
            'note' => 'برای مشاهده Slotها، Schedule انتخاب‌شده را بازبینی کنید؛ عملیات ایجاد/ویرایش Slot از همین فرم انجام می‌شود.'
        ],
        'facility-rules' => [
            'group' => 'facility',
            'title' => 'قوانین رزرو Facility',
            'description' => 'محدودیت مدت، ظرفیت رزرو و لغو',
            'mode' => 'singleton',
            'context' => [
                [
                    'name' => 'building_facility_id',
                    'label' => 'Facility',
                    'lookup' => 'facilities',
                    'required' => true
                ]
            ],
            'show' => [
                'method' => 'GET',
                'url' => '/api/v1/facilities/{building_facility_id}/reservation-rule'
            ],
            'create' => [
                'method' => 'PUT',
                'url' => '/api/v1/facilities/{building_facility_id}/reservation-rule'
            ],
            'fields' => [
                [
                    'name' => 'min_duration_minutes',
                    'label' => 'حداقل مدت (دقیقه)',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_duration_minutes',
                    'label' => 'حداکثر مدت',
                    'type' => 'number'
                ],
                [
                    'name' => 'min_advance_minutes',
                    'label' => 'حداقل زمان رزرو قبل از استفاده',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_advance_days',
                    'label' => 'حداکثر روزهای پیش‌رزرو',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_reservations_per_day',
                    'label' => 'حداکثر رزرو روزانه',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_reservations_per_week',
                    'label' => 'هفتگی',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_reservations_per_month',
                    'label' => 'ماهانه',
                    'type' => 'number'
                ],
                [
                    'name' => 'max_reservation_per_unit',
                    'label' => 'حداکثر رزرو هر واحد',
                    'type' => 'number'
                ],
                [
                    'name' => 'cancel_before_minutes',
                    'label' => 'مهلت لغو',
                    'type' => 'number'
                ],
                [
                    'name' => 'cancellation_fee',
                    'label' => 'هزینه لغو',
                    'type' => 'number'
                ],
                [
                    'name' => 'refund_percentage',
                    'label' => 'درصد بازپرداخت',
                    'type' => 'number'
                ],
                [
                    'name' => 'allow_guest',
                    'label' => 'اجازه مهمان',
                    'type' => 'checkbox',
                    'default' => false
                ],
                [
                    'name' => 'auto_confirm',
                    'label' => 'تأیید خودکار',
                    'type' => 'checkbox',
                    'default' => false
                ]
            ]
        ],
        'facility-blackouts' => [
            'group' => 'facility',
            'title' => 'Blackout Facility',
            'description' => 'مسدودسازی زمان‌های غیرقابل رزرو',
            'context' => [
                [
                    'name' => 'building_facility_id',
                    'label' => 'Facility',
                    'lookup' => 'facilities',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/facilities/{building_facility_id}/blackouts'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/facilities/{building_facility_id}/blackouts'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/facilities/{building_facility_id}/blackouts/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'starts_at',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'ends_at',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'reason',
                    'label' => 'علت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'starts_at',
                    'label' => 'شروع',
                    'type' => 'datetime-local',
                    'required' => true
                ],
                [
                    'name' => 'ends_at',
                    'label' => 'پایان',
                    'type' => 'datetime-local',
                    'required' => true
                ],
                [
                    'name' => 'reason',
                    'label' => 'علت',
                    'type' => 'textarea'
                ]
            ]
        ],
        'reservations' => [
            'group' => 'facility',
            'title' => 'رزرو امکانات',
            'description' => 'ثبت رزرو و مدیریت Workflow',
            'context' => [
                [
                    'name' => 'building_facility_id',
                    'label' => 'Facility برای ثبت رزرو',
                    'lookup' => 'facilities',
                    'required' => false
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/facility-reservations?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/facilities/{building_facility_id}/reservations'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'building_facility.title',
                    'label' => 'Facility'
                ],
                [
                    'key' => 'unit.unit_number',
                    'label' => 'واحد'
                ],
                [
                    'key' => 'reservation_date',
                    'label' => 'تاریخ'
                ],
                [
                    'key' => 'start_time',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'end_time',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'final_amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'type' => 'select',
                    'lookup' => 'units',
                    'required' => true
                ],
                [
                    'name' => 'facility_time_slot_id',
                    'label' => 'Time Slot ID',
                    'type' => 'number'
                ],
                [
                    'name' => 'reservation_date',
                    'label' => 'تاریخ',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'start_time',
                    'label' => 'شروع',
                    'type' => 'time'
                ],
                [
                    'name' => 'end_time',
                    'label' => 'پایان',
                    'type' => 'time'
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'approve',
                    'title' => 'تأیید',
                    'method' => 'POST',
                    'url' => '/api/v1/facility-reservations/{id}/approve',
                    'tone' => 'success'
                ],
                [
                    'key' => 'reject',
                    'title' => 'رد',
                    'method' => 'POST',
                    'url' => '/api/v1/facility-reservations/{id}/reject',
                    'tone' => 'danger',
                    'fields' => [
                        [
                            'name' => 'reason',
                            'label' => 'علت رد',
                            'type' => 'textarea'
                        ]
                    ]
                ],
                [
                    'key' => 'pay',
                    'title' => 'پرداخت از کیف پول',
                    'method' => 'POST',
                    'url' => '/api/v1/facility-reservations/{id}/pay',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'payer_source',
                            'label' => 'منبع پرداخت',
                            'type' => 'select',
                            'required' => true,
                            'options' => [
                                [
                                    'value' => 'user_wallet',
                                    'label' => 'کیف پول کاربر'
                                ],
                                [
                                    'value' => 'unit_wallet',
                                    'label' => 'کیف پول واحد'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'key' => 'cancel',
                    'title' => 'لغو',
                    'method' => 'POST',
                    'url' => '/api/v1/facility-reservations/{id}/cancel',
                    'tone' => 'danger',
                    'fields' => [
                        [
                            'name' => 'reason',
                            'label' => 'علت',
                            'type' => 'textarea'
                        ]
                    ]
                ]
            ]
        ],
        'charge-formulas' => [
            'group' => 'finance',
            'title' => 'فرمول شارژ',
            'description' => 'تعریف روش محاسبه شارژ',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/charge-formulas'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/charge-formulas'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/charge-formulas/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'calculation_type',
                    'label' => 'روش'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'calculation_type',
                    'label' => 'روش',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'fixed',
                            'label' => 'ثابت'
                        ],
                        [
                            'value' => 'area',
                            'label' => 'متراژ'
                        ],
                        [
                            'value' => 'persons',
                            'label' => 'نفرات'
                        ],
                        [
                            'value' => 'equal',
                            'label' => 'مساوی'
                        ],
                        [
                            'value' => 'custom',
                            'label' => 'سفارشی'
                        ]
                    ]
                ],
                [
                    'name' => 'configuration',
                    'label' => 'Configuration',
                    'type' => 'json',
                    'placeholder' => '{}'
                ],
                [
                    'name' => 'items',
                    'label' => 'آیتم‌های فرمول',
                    'type' => 'json',
                    'required' => true,
                    'placeholder' => '[{"financial_category_id":null,"title":"شارژ پایه","base_amount":1000000,"configuration":{}}]'
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'charge-periods' => [
            'group' => 'finance',
            'title' => 'دوره‌های شارژ',
            'description' => 'ایجاد دوره، محاسبه و صدور شارژ',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/charge-periods'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/charge-periods'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/charge-periods/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'period_start',
                    'label' => 'شروع'
                ],
                [
                    'key' => 'period_end',
                    'label' => 'پایان'
                ],
                [
                    'key' => 'due_date',
                    'label' => 'سررسید'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'period_start',
                    'label' => 'شروع دوره',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'period_end',
                    'label' => 'پایان دوره',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'due_date',
                    'label' => 'سررسید',
                    'type' => 'date',
                    'required' => true
                ]
            ],
            'actions' => [
                [
                    'key' => 'calculate',
                    'title' => 'محاسبه',
                    'method' => 'POST',
                    'url' => '/api/v1/charge-periods/{id}/calculate',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'issue',
                    'title' => 'صدور',
                    'method' => 'POST',
                    'url' => '/api/v1/charge-periods/{id}/issue',
                    'tone' => 'success',
                    'confirm' => 'صورتحساب‌های این دوره صادر شوند؟'
                ]
            ]
        ],
        'invoices' => [
            'group' => 'finance',
            'title' => 'صورتحساب واحد',
            'description' => 'ثبت و مدیریت Invoice واحد',
            'context' => [
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'lookup' => 'units',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/units/{unit_id}/invoices'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/units/{unit_id}/invoices'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/invoices/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/invoices/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'invoice_number',
                    'label' => 'شماره'
                ],
                [
                    'key' => 'issue_date',
                    'label' => 'صدور'
                ],
                [
                    'key' => 'due_date',
                    'label' => 'سررسید'
                ],
                [
                    'key' => 'total_amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'outstanding_amount',
                    'label' => 'مانده'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'issue_date',
                    'label' => 'تاریخ صدور',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'due_date',
                    'label' => 'سررسید',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'period_start',
                    'label' => 'شروع دوره',
                    'type' => 'date'
                ],
                [
                    'name' => 'period_end',
                    'label' => 'پایان دوره',
                    'type' => 'date'
                ],
                [
                    'name' => 'discount_amount',
                    'label' => 'تخفیف',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'penalty_amount',
                    'label' => 'جریمه',
                    'type' => 'number',
                    'default' => 0
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'items',
                    'label' => 'آیتم‌ها',
                    'type' => 'json',
                    'required' => true,
                    'placeholder' => '[{"title":"شارژ ماهانه","quantity":1,"unit_amount":1500000}]'
                ]
            ],
            'actions' => [
                [
                    'key' => 'issue',
                    'title' => 'صدور Invoice',
                    'method' => 'POST',
                    'url' => '/api/v1/invoices/{id}/issue',
                    'tone' => 'success'
                ]
            ]
        ],
        'expenses' => [
            'group' => 'finance',
            'title' => 'هزینه‌های ساختمان',
            'description' => 'ثبت هزینه‌های عملیاتی',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/expenses?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/expenses'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/expenses/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/expenses/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'building_id',
                    'label' => 'ساختمان'
                ],
                [
                    'key' => 'amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'expense_date',
                    'label' => 'تاریخ'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'type' => 'select',
                    'lookup' => 'buildings',
                    'required' => true
                ],
                [
                    'name' => 'fund_id',
                    'label' => 'صندوق',
                    'type' => 'select',
                    'lookup' => 'funds'
                ],
                [
                    'name' => 'financial_category_id',
                    'label' => 'دسته مالی',
                    'type' => 'select',
                    'lookup' => 'financial_categories'
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'amount',
                    'label' => 'مبلغ',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'expense_date',
                    'label' => 'تاریخ',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'invoice_number',
                    'label' => 'شماره فاکتور',
                    'type' => 'text'
                ],
                [
                    'name' => 'status',
                    'label' => 'وضعیت',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'draft',
                            'label' => 'پیش‌نویس'
                        ],
                        [
                            'value' => 'approved',
                            'label' => 'تأیید'
                        ],
                        [
                            'value' => 'posted',
                            'label' => 'ثبت‌شده'
                        ],
                        [
                            'value' => 'cancelled',
                            'label' => 'لغو'
                        ]
                    ]
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ]
        ],
        'incomes' => [
            'group' => 'finance',
            'title' => 'درآمدهای ساختمان',
            'description' => 'ثبت درآمدهای غیرشارژی/عملیاتی',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/incomes?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/incomes'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/incomes/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/incomes/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'building_id',
                    'label' => 'ساختمان'
                ],
                [
                    'key' => 'amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'income_date',
                    'label' => 'تاریخ'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'type' => 'select',
                    'lookup' => 'buildings',
                    'required' => true
                ],
                [
                    'name' => 'fund_id',
                    'label' => 'صندوق',
                    'type' => 'select',
                    'lookup' => 'funds'
                ],
                [
                    'name' => 'financial_category_id',
                    'label' => 'دسته مالی',
                    'type' => 'select',
                    'lookup' => 'financial_categories'
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'amount',
                    'label' => 'مبلغ',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'income_date',
                    'label' => 'تاریخ',
                    'type' => 'date',
                    'required' => true
                ],
                [
                    'name' => 'reference_number',
                    'label' => 'شماره مرجع',
                    'type' => 'text'
                ],
                [
                    'name' => 'status',
                    'label' => 'وضعیت',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'draft',
                            'label' => 'پیش‌نویس'
                        ],
                        [
                            'value' => 'approved',
                            'label' => 'تأیید'
                        ],
                        [
                            'value' => 'posted',
                            'label' => 'ثبت‌شده'
                        ],
                        [
                            'value' => 'cancelled',
                            'label' => 'لغو'
                        ]
                    ]
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ]
        ],
        'payments' => [
            'group' => 'finance',
            'title' => 'پرداخت‌ها',
            'description' => 'ثبت پرداخت Invoice و Verify',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان برای فهرست',
                    'lookup' => 'buildings',
                    'required' => false
                ],
                [
                    'name' => 'unit_invoice_id',
                    'label' => 'Invoice برای ثبت',
                    'lookup' => 'invoices',
                    'required' => false
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/payments?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/invoices/{unit_invoice_id}/payments'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'payment_number',
                    'label' => 'شماره'
                ],
                [
                    'key' => 'amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'method',
                    'label' => 'روش'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'paid_at',
                    'label' => 'پرداخت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'amount',
                    'label' => 'مبلغ',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'method',
                    'label' => 'روش پرداخت',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'online',
                            'label' => 'آنلاین'
                        ],
                        [
                            'value' => 'manual',
                            'label' => 'دستی'
                        ],
                        [
                            'value' => 'cash',
                            'label' => 'نقدی'
                        ],
                        [
                            'value' => 'bank_transfer',
                            'label' => 'انتقال بانکی'
                        ],
                        [
                            'value' => 'pos',
                            'label' => 'POS'
                        ],
                        [
                            'value' => 'qr',
                            'label' => 'QR'
                        ],
                        [
                            'value' => 'bill',
                            'label' => 'قبض'
                        ],
                        [
                            'value' => 'installment',
                            'label' => 'اقساط'
                        ]
                    ]
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'verify',
                    'title' => 'Verify پرداخت',
                    'method' => 'POST',
                    'url' => '/api/v1/payments/{id}/verify',
                    'tone' => 'success'
                ]
            ]
        ],
        'bank-accounts' => [
            'group' => 'finance',
            'title' => 'حساب بانکی ساختمان',
            'description' => 'ثبت و تأیید حساب‌های مقصد تسویه',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/bank-accounts'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/bank-accounts'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'bank_name',
                    'label' => 'بانک'
                ],
                [
                    'key' => 'account_holder_name',
                    'label' => 'صاحب حساب'
                ],
                [
                    'key' => 'iban',
                    'label' => 'شبا'
                ],
                [
                    'key' => 'is_verified',
                    'label' => 'تأیید'
                ],
                [
                    'key' => 'is_default',
                    'label' => 'پیش‌فرض'
                ]
            ],
            'fields' => [
                [
                    'name' => 'bank_name',
                    'label' => 'نام بانک',
                    'type' => 'text'
                ],
                [
                    'name' => 'account_holder_name',
                    'label' => 'صاحب حساب',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'iban',
                    'label' => 'شماره شبا',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'account_number',
                    'label' => 'شماره حساب',
                    'type' => 'text'
                ],
                [
                    'name' => 'card_number',
                    'label' => 'شماره کارت',
                    'type' => 'text'
                ],
                [
                    'name' => 'is_default',
                    'label' => 'پیش‌فرض',
                    'type' => 'checkbox',
                    'default' => false
                ]
            ],
            'actions' => [
                [
                    'key' => 'verify',
                    'title' => 'تأیید حساب',
                    'method' => 'POST',
                    'url' => '/api/v1/building-bank-accounts/{id}/verify',
                    'tone' => 'success'
                ]
            ]
        ],
        'wallet-payouts' => [
            'group' => 'finance',
            'title' => 'برداشت از کیف پول ساختمان',
            'description' => 'درخواست، تأیید و ثبت پرداخت بانکی',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/wallet-payouts'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/wallet-payouts'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'requested_at',
                    'label' => 'درخواست'
                ],
                [
                    'key' => 'paid_at',
                    'label' => 'پرداخت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_bank_account_id',
                    'label' => 'حساب بانکی',
                    'type' => 'select',
                    'lookup' => 'building_bank_accounts',
                    'required' => true
                ],
                [
                    'name' => 'amount',
                    'label' => 'مبلغ',
                    'type' => 'number',
                    'required' => true
                ]
            ],
            'actions' => [
                [
                    'key' => 'approve',
                    'title' => 'تأیید',
                    'method' => 'POST',
                    'url' => '/api/v1/wallet-payouts/{id}/approve',
                    'tone' => 'success'
                ],
                [
                    'key' => 'reject',
                    'title' => 'رد',
                    'method' => 'POST',
                    'url' => '/api/v1/wallet-payouts/{id}/reject',
                    'tone' => 'danger',
                    'fields' => [
                        [
                            'name' => 'reason',
                            'label' => 'علت',
                            'type' => 'textarea'
                        ]
                    ]
                ],
                [
                    'key' => 'paid',
                    'title' => 'ثبت پرداخت',
                    'method' => 'POST',
                    'url' => '/api/v1/wallet-payouts/{id}/paid',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'bank_reference',
                            'label' => 'مرجع بانکی',
                            'type' => 'text'
                        ]
                    ]
                ]
            ]
        ],
        'bill-payments' => [
            'group' => 'finance',
            'title' => 'پرداخت قبوض',
            'description' => 'کسر قبض از Wallet ساختمان',
            'context' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'lookup' => 'buildings',
                    'required' => true
                ]
            ],
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/buildings/{building_id}/bill-payments'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/buildings/{building_id}/bill-payments'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'bill_type',
                    'label' => 'نوع قبض'
                ],
                [
                    'key' => 'amount',
                    'label' => 'مبلغ'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'provider',
                    'label' => 'ارائه‌دهنده'
                ]
            ],
            'fields' => [
                [
                    'name' => 'bill_type',
                    'label' => 'نوع قبض',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'electricity',
                            'label' => 'برق'
                        ],
                        [
                            'value' => 'water',
                            'label' => 'آب'
                        ],
                        [
                            'value' => 'gas',
                            'label' => 'گاز'
                        ],
                        [
                            'value' => 'phone',
                            'label' => 'تلفن'
                        ],
                        [
                            'value' => 'internet',
                            'label' => 'اینترنت'
                        ],
                        [
                            'value' => 'municipality',
                            'label' => 'شهرداری'
                        ],
                        [
                            'value' => 'other',
                            'label' => 'سایر'
                        ]
                    ]
                ],
                [
                    'name' => 'bill_identifier',
                    'label' => 'شناسه قبض',
                    'type' => 'text'
                ],
                [
                    'name' => 'payment_identifier',
                    'label' => 'شناسه پرداخت',
                    'type' => 'text'
                ],
                [
                    'name' => 'amount',
                    'label' => 'مبلغ',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'provider',
                    'label' => 'ارائه‌دهنده',
                    'type' => 'text'
                ]
            ],
            'actions' => [
                [
                    'key' => 'complete',
                    'title' => 'تکمیل پرداخت',
                    'method' => 'POST',
                    'url' => '/api/v1/building-bill-payments/{id}/complete',
                    'tone' => 'success',
                    'fields' => [
                        [
                            'name' => 'provider_reference',
                            'label' => 'مرجع پرداخت',
                            'type' => 'text'
                        ]
                    ]
                ],
                [
                    'key' => 'fail',
                    'title' => 'ناموفق',
                    'method' => 'POST',
                    'url' => '/api/v1/building-bill-payments/{id}/fail',
                    'tone' => 'danger',
                    'fields' => [
                        [
                            'name' => 'reason',
                            'label' => 'علت',
                            'type' => 'textarea'
                        ]
                    ]
                ]
            ]
        ],
        'service-requests' => [
            'group' => 'services',
            'title' => 'درخواست خدمات',
            'description' => 'ثبت و مدیریت Service Request تا تسویه',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/service-requests?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/service-requests'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/service-requests/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/service-requests/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'request_number',
                    'label' => 'شماره'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'building_id',
                    'label' => 'ساختمان'
                ],
                [
                    'key' => 'priority',
                    'label' => 'اولویت'
                ],
                [
                    'key' => 'assigned_to',
                    'label' => 'ارائه‌دهنده'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'type' => 'select',
                    'lookup' => 'buildings',
                    'required' => true
                ],
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'type' => 'select',
                    'lookup' => 'units'
                ],
                [
                    'name' => 'type',
                    'label' => 'نوع خدمت',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'priority',
                    'label' => 'اولویت',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'low',
                            'label' => 'کم'
                        ],
                        [
                            'value' => 'normal',
                            'label' => 'عادی'
                        ],
                        [
                            'value' => 'high',
                            'label' => 'بالا'
                        ],
                        [
                            'value' => 'urgent',
                            'label' => 'فوری'
                        ]
                    ]
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'description',
                    'label' => 'شرح',
                    'type' => 'textarea'
                ]
            ],
            'actions' => [
                [
                    'key' => 'assign',
                    'title' => 'تخصیص Provider',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/assign',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'assigned_to',
                            'label' => 'ارائه‌دهنده',
                            'type' => 'select',
                            'lookup' => 'users',
                            'required' => true
                        ]
                    ]
                ],
                [
                    'key' => 'quote',
                    'title' => 'ثبت Quote',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/quotes',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'amount',
                            'label' => 'مبلغ',
                            'type' => 'number',
                            'required' => true
                        ],
                        [
                            'name' => 'valid_until',
                            'label' => 'اعتبار تا',
                            'type' => 'datetime-local'
                        ],
                        [
                            'name' => 'notes',
                            'label' => 'یادداشت',
                            'type' => 'textarea'
                        ]
                    ]
                ],
                [
                    'key' => 'start',
                    'title' => 'شروع خدمت',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/start',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'finish',
                    'title' => 'پایان خدمت',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/finish',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'confirm',
                    'title' => 'تأیید نهایی',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/confirm',
                    'tone' => 'success'
                ],
                [
                    'key' => 'cancel-financial',
                    'title' => 'لغو مالی',
                    'method' => 'POST',
                    'url' => '/api/v1/service-requests/{id}/cancel-financial',
                    'tone' => 'danger',
                    'fields' => [
                        [
                            'name' => 'reason',
                            'label' => 'علت',
                            'type' => 'textarea'
                        ]
                    ]
                ]
            ]
        ],
        'support-tickets' => [
            'group' => 'support',
            'title' => 'تیکت‌های پشتیبانی',
            'description' => 'ثبت، تخصیص و چرخه SLA',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/support-tickets?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/support-tickets'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/support-tickets/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/support-tickets/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'ticket_number',
                    'label' => 'شماره'
                ],
                [
                    'key' => 'subject',
                    'label' => 'موضوع'
                ],
                [
                    'key' => 'priority',
                    'label' => 'اولویت'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'assigned_to',
                    'label' => 'مسئول'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'type' => 'select',
                    'lookup' => 'buildings'
                ],
                [
                    'name' => 'unit_id',
                    'label' => 'واحد',
                    'type' => 'select',
                    'lookup' => 'units'
                ],
                [
                    'name' => 'support_category_id',
                    'label' => 'دسته‌بندی',
                    'type' => 'select',
                    'lookup' => 'support_categories'
                ],
                [
                    'name' => 'subject',
                    'label' => 'موضوع',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'description',
                    'label' => 'شرح',
                    'type' => 'textarea',
                    'required' => true
                ],
                [
                    'name' => 'priority',
                    'label' => 'اولویت',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'low',
                            'label' => 'کم'
                        ],
                        [
                            'value' => 'medium',
                            'label' => 'متوسط'
                        ],
                        [
                            'value' => 'high',
                            'label' => 'بالا'
                        ],
                        [
                            'value' => 'urgent',
                            'label' => 'فوری'
                        ]
                    ]
                ]
            ],
            'actions' => [
                [
                    'key' => 'assign',
                    'title' => 'تخصیص',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/assign',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'assigned_to',
                            'label' => 'کارشناس',
                            'type' => 'select',
                            'lookup' => 'users',
                            'required' => true
                        ]
                    ]
                ],
                [
                    'key' => 'message',
                    'title' => 'ارسال پیام',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/messages',
                    'tone' => 'primary',
                    'fields' => [
                        [
                            'name' => 'message',
                            'label' => 'پیام',
                            'type' => 'textarea',
                            'required' => true
                        ],
                        [
                            'name' => 'is_internal',
                            'label' => 'یادداشت داخلی',
                            'type' => 'checkbox',
                            'default' => false
                        ]
                    ]
                ],
                [
                    'key' => 'start',
                    'title' => 'شروع رسیدگی',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/start',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'wait-user',
                    'title' => 'انتظار کاربر',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/wait-user',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'resolve',
                    'title' => 'حل تیکت',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/resolve',
                    'tone' => 'success',
                    'fields' => [
                        [
                            'name' => 'resolution',
                            'label' => 'شرح راه‌حل',
                            'type' => 'textarea',
                            'required' => true
                        ]
                    ]
                ],
                [
                    'key' => 'close',
                    'title' => 'بستن',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/close',
                    'tone' => 'success'
                ],
                [
                    'key' => 'reopen',
                    'title' => 'بازگشایی',
                    'method' => 'POST',
                    'url' => '/api/v1/support-tickets/{id}/reopen',
                    'tone' => 'primary'
                ]
            ]
        ],
        'support-categories' => [
            'group' => 'support',
            'title' => 'دسته‌بندی پشتیبانی',
            'description' => 'تعریف Categoryهای تیکت',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/support-config/categories'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/support-config/categories'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/support-config/categories/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'support-sla' => [
            'group' => 'support',
            'title' => 'SLA پشتیبانی',
            'description' => 'زمان هدف پاسخ و حل تیکت',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/support-config/sla-policies'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/support-config/sla-policies'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/support-config/sla-policies/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'support_category_id',
                    'label' => 'دسته'
                ],
                [
                    'key' => 'priority',
                    'label' => 'اولویت'
                ],
                [
                    'key' => 'first_response_minutes',
                    'label' => 'پاسخ اولیه'
                ],
                [
                    'key' => 'resolution_minutes',
                    'label' => 'حل'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'support_category_id',
                    'label' => 'دسته',
                    'type' => 'select',
                    'lookup' => 'support_categories'
                ],
                [
                    'name' => 'priority',
                    'label' => 'اولویت',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'low',
                            'label' => 'کم'
                        ],
                        [
                            'value' => 'medium',
                            'label' => 'متوسط'
                        ],
                        [
                            'value' => 'high',
                            'label' => 'بالا'
                        ],
                        [
                            'value' => 'urgent',
                            'label' => 'فوری'
                        ]
                    ]
                ],
                [
                    'name' => 'first_response_minutes',
                    'label' => 'زمان پاسخ (دقیقه)',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'resolution_minutes',
                    'label' => 'زمان حل (دقیقه)',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'announcements' => [
            'group' => 'content',
            'title' => 'اطلاعیه‌ها',
            'description' => 'ثبت و انتشار اطلاعیه',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/announcements?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/announcements'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/announcements/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/announcements/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'type',
                    'label' => 'نوع'
                ],
                [
                    'key' => 'priority',
                    'label' => 'اولویت'
                ],
                [
                    'key' => 'published_at',
                    'label' => 'انتشار'
                ],
                [
                    'key' => 'is_active',
                    'label' => 'فعال'
                ]
            ],
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'content',
                    'label' => 'متن',
                    'type' => 'textarea',
                    'required' => true
                ],
                [
                    'name' => 'type',
                    'label' => 'نوع',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'general',
                            'label' => 'عمومی'
                        ],
                        [
                            'value' => 'urgent',
                            'label' => 'فوری'
                        ],
                        [
                            'value' => 'maintenance',
                            'label' => 'نگهداری'
                        ],
                        [
                            'value' => 'financial',
                            'label' => 'مالی'
                        ]
                    ]
                ],
                [
                    'name' => 'priority',
                    'label' => 'اولویت',
                    'type' => 'select',
                    'options' => [
                        [
                            'value' => 'low',
                            'label' => 'کم'
                        ],
                        [
                            'value' => 'normal',
                            'label' => 'عادی'
                        ],
                        [
                            'value' => 'high',
                            'label' => 'بالا'
                        ],
                        [
                            'value' => 'urgent',
                            'label' => 'فوری'
                        ]
                    ]
                ],
                [
                    'name' => 'starts_at',
                    'label' => 'شروع',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'expires_at',
                    'label' => 'انقضا',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'published_at',
                    'label' => 'انتشار',
                    'type' => 'datetime-local'
                ],
                [
                    'name' => 'is_active',
                    'label' => 'فعال',
                    'type' => 'checkbox',
                    'default' => true
                ]
            ]
        ],
        'documents' => [
            'group' => 'content',
            'title' => 'اسناد',
            'description' => 'ثبت Document Record روی موجودیت‌های سامانه',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/documents?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/documents'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/documents/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/documents/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'document_type',
                    'label' => 'نوع'
                ],
                [
                    'key' => 'document_number',
                    'label' => 'شماره'
                ],
                [
                    'key' => 'document_date',
                    'label' => 'تاریخ'
                ],
                [
                    'key' => 'expires_at',
                    'label' => 'انقضا'
                ]
            ],
            'fields' => [
                [
                    'name' => 'documentable_type',
                    'label' => 'نوع موجودیت',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'App\\Models\\Building'
                ],
                [
                    'name' => 'documentable_id',
                    'label' => 'شناسه موجودیت',
                    'type' => 'number',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'document_type',
                    'label' => 'نوع سند',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        [
                            'value' => 'building',
                            'label' => 'ساختمان'
                        ],
                        [
                            'value' => 'unit',
                            'label' => 'واحد'
                        ],
                        [
                            'value' => 'contract',
                            'label' => 'قرارداد'
                        ],
                        [
                            'value' => 'ownership',
                            'label' => 'مالکیت'
                        ],
                        [
                            'value' => 'lease',
                            'label' => 'اجاره'
                        ],
                        [
                            'value' => 'meeting_minute',
                            'label' => 'صورتجلسه'
                        ],
                        [
                            'value' => 'financial',
                            'label' => 'مالی'
                        ],
                        [
                            'value' => 'other',
                            'label' => 'سایر'
                        ]
                    ]
                ],
                [
                    'name' => 'document_number',
                    'label' => 'شماره سند',
                    'type' => 'text'
                ],
                [
                    'name' => 'document_date',
                    'label' => 'تاریخ سند',
                    'type' => 'date'
                ],
                [
                    'name' => 'expires_at',
                    'label' => 'انقضا',
                    'type' => 'date'
                ],
                [
                    'name' => 'description',
                    'label' => 'توضیحات',
                    'type' => 'textarea'
                ]
            ]
        ],
        'meeting-minutes' => [
            'group' => 'content',
            'title' => 'صورتجلسات',
            'description' => 'ثبت صورتجلسات ساختمان',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/meeting-minutes?per_page=100'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/meeting-minutes'
            ],
            'update' => [
                'method' => 'PATCH',
                'url' => '/api/v1/meeting-minutes/{id}'
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => '/api/v1/meeting-minutes/{id}'
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'title',
                    'label' => 'عنوان'
                ],
                [
                    'key' => 'building_id',
                    'label' => 'ساختمان'
                ],
                [
                    'key' => 'meeting_at',
                    'label' => 'تاریخ جلسه'
                ],
                [
                    'key' => 'meeting_number',
                    'label' => 'شماره'
                ]
            ],
            'fields' => [
                [
                    'name' => 'building_id',
                    'label' => 'ساختمان',
                    'type' => 'select',
                    'lookup' => 'buildings',
                    'required' => true
                ],
                [
                    'name' => 'title',
                    'label' => 'عنوان',
                    'type' => 'text',
                    'required' => true
                ],
                [
                    'name' => 'meeting_at',
                    'label' => 'زمان جلسه',
                    'type' => 'datetime-local',
                    'required' => true
                ],
                [
                    'name' => 'meeting_number',
                    'label' => 'شماره جلسه',
                    'type' => 'text'
                ],
                [
                    'name' => 'content',
                    'label' => 'متن صورتجلسه',
                    'type' => 'textarea'
                ]
            ]
        ],
        'notification-preferences' => [
            'group' => 'content',
            'title' => 'تنظیم اعلان شخصی',
            'description' => 'مدیریت کانال‌های اعلان حساب جاری',
        'singleton_wrap' => 'preferences',
            'mode' => 'singleton',
            'show' => [
                'method' => 'GET',
                'url' => '/api/v1/notification-preferences'
            ],
            'create' => [
                'method' => 'PUT',
                'url' => '/api/v1/notification-preferences'
            ],
            'fields' => [
                [
                    'name' => 'preferences',
                    'label' => 'Preferences',
                    'type' => 'json',
                    'required' => true,
                    'placeholder' => '[{"notification_type":"charge_due","channel":"database","is_enabled":true}]'
                ]
            ]
        ],
        'report-exports' => [
            'group' => 'reports',
            'title' => 'خروجی گزارش',
            'description' => 'تولید و مشاهده CSV/Excel/PDF',
            'list' => [
                'method' => 'GET',
                'url' => '/api/v1/report-exports'
            ],
            'create' => [
                'method' => 'POST',
                'url' => '/api/v1/report-definitions/{report_definition_id}/exports'
            ],
            'context' => [
                [
                    'name' => 'report_definition_id',
                    'label' => 'تعریف گزارش',
                    'lookup' => 'report_definitions',
                    'required' => false
                ]
            ],
            'columns' => [
                [
                    'key' => 'id',
                    'label' => 'ID'
                ],
                [
                    'key' => 'format',
                    'label' => 'فرمت'
                ],
                [
                    'key' => 'status',
                    'label' => 'وضعیت'
                ],
                [
                    'key' => 'building_id',
                    'label' => 'ساختمان'
                ],
                [
                    'key' => 'generated_at',
                    'label' => 'تولید'
                ]
            ],
            'fields' => [
            [
                'name' => 'building_id',
                'label' => 'ساختمان',
                'type' => 'select',
                'lookup' => 'buildings'
            ],
            [
                'name' => 'format',
                'label' => 'فرمت',
                'type' => 'select',
                'required' => true,
                'options' => [
                    [
                        'value' => 'csv',
                        'label' => 'CSV'
                    ],
                    [
                        'value' => 'excel',
                        'label' => 'Excel'
                    ],
                    [
                        'value' => 'pdf',
                        'label' => 'PDF'
                    ]
                ]
            ],
            [
                'name' => 'from',
                'label' => 'از تاریخ',
                'type' => 'date'
            ],
            [
                'name' => 'to',
                'label' => 'تا تاریخ',
                'type' => 'date'
            ],
            [
                'name' => 'as_of',
                'label' => 'تاریخ مبنا',
                'type' => 'date'
            ],
            [
                'name' => 'granularity',
                'label' => 'دانه‌بندی',
                'type' => 'select',
                'options' => [
                    [
                        'value' => 'day',
                        'label' => 'روز'
                    ],
                    [
                        'value' => 'month',
                        'label' => 'ماه'
                    ]
                ]
            ],
            [
                'name' => 'currency',
                'label' => 'ارز',
                'type' => 'text',
                'default' => 'IRR'
            ]
        ],
        'actions' => [
                [
                    'key' => 'retry',
                    'title' => 'تلاش مجدد',
                    'method' => 'POST',
                    'url' => '/api/v1/report-exports/{id}/retry',
                    'tone' => 'warning'
                ],
                [
                    'key' => 'delete',
                    'title' => 'حذف خروجی',
                    'method' => 'DELETE',
                    'url' => '/api/v1/report-exports/{id}',
                    'tone' => 'danger',
                    'confirm' => 'خروجی حذف شود؟'
                ]
            ]
        ]
    ]
];
