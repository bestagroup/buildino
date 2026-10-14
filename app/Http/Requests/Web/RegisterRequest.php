<?php

namespace App\Http\Requests\Web;

use App\Services\System\IranLocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $locations = app(IranLocations::class);
        $province = $this->has('province_id')
            ? $locations->province($this->input('province_id'))
            : $locations->provinceByName($this->input('province'));
        $city = $this->has('city_id')
            ? $locations->city($this->input('city_id'))
            : $locations->cityByName($province['name'] ?? null, $this->input('city'));

        $this->merge([
            'first_name' => $this->cleanText(
                $this->input('first_name')
            ),
            'last_name' => $this->cleanText(
                $this->input('last_name')
            ),
            'mobile' => $this->normalizeMobile(
                $this->input('mobile')
            ),
            'email' => $this->normalizeEmail(
                $this->input('email')
            ),
            'invitation_token' => $this->cleanText(
                $this->input('invitation_token')
            ),
            'province' => $province['name'] ?? ($this->has('province_id') ? null : $this->input('province')),
            'city' => $city['name'] ?? ($this->has('city_id') ? null : $this->input('city')),
        ]);
    }

    public function rules(): array
    {
        $locations = app(IranLocations::class);
        $personas = config(
            'self_registration.personas',
            []
        );

        $managementPersonas = collect($personas)
            ->filter(
                fn (array $persona): bool => ($persona['kind'] ?? null)
                        === 'management'
            )
            ->keys()
            ->all();

        $residentPersonas = collect($personas)
            ->filter(
                fn (array $persona): bool => ($persona['kind'] ?? null)
                        === 'resident'
            )
            ->keys()
            ->all();

        return [
            'province_id' => [
                'bail',
                'nullable',
                'required_with:city_id',
                'integer',
                Rule::in(array_keys($locations->provinces())),
            ],
            'city_id' => [
                'bail',
                'nullable',
                'required_with:province_id',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($locations): void {
                    $city = $locations->city($value);
                    $province = $locations->province($this->input('province_id'));
                    if (! $city || ! $province || $city['province_id'] !== $province['id']) {
                        $fail('شهر انتخاب‌شده متعلق به استان انتخاب‌شده نیست.');
                    }
                },
            ],
            'persona' => [
                'required',
                Rule::in(array_keys($personas)),
            ],
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],
            'last_name' => [
                'required',
                'string',
                'max:100',
            ],
            'mobile' => [
                'required',
                'regex:/^09\d{9}$/',
                Rule::unique('users', 'mobile'),
            ],
            'email' => [
                'nullable',
                'email:rfc',
                'max:190',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
                'max:255',
            ],
            'complex_title' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('persona'),
                        $managementPersonas,
                        true
                    )
                ),
                'nullable',
                'string',
                'max:255',
            ],
            'building_title' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('persona'),
                        $managementPersonas,
                        true
                    )
                ),
                'nullable',
                'string',
                'max:255',
            ],
            'province' => [
                'bail',
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('persona'),
                        $managementPersonas,
                        true
                    )
                ),
                'nullable',
                'string',
                'max:100',
                Rule::in(array_column($locations->provinces(), 'name')),
            ],
            'city' => [
                'bail',
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('persona'),
                        $managementPersonas,
                        true
                    )
                ),
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($locations): void {
                    if (! $locations->cityByName($this->input('province'), $value)) {
                        $fail('شهر را از فهرست شهرهای استان انتخاب‌شده انتخاب کنید.');
                    }
                },
            ],
            'address' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'invitation_token' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('persona'),
                        $residentPersonas,
                        true
                    )
                ),
                'nullable',
                'string',
                'min:32',
                'max:255',
            ],
            'terms' => [
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'persona.required' => 'نوع حساب را انتخاب کنید.',
            'persona.in' => 'نوع حساب انتخاب‌شده قابل ثبت‌نام عمومی نیست.',
            'first_name.required' => 'نام را وارد کنید.',
            'last_name.required' => 'نام خانوادگی را وارد کنید.',
            'mobile.required' => 'شماره موبایل را وارد کنید.',
            'mobile.regex' => 'شماره موبایل باید با 09 شروع شود و 11 رقم باشد.',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است؛ از صفحه ورود استفاده کنید.',
            'email.email' => 'ایمیل واردشده معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'password.confirmed' => 'تکرار رمز عبور با رمز عبور یکسان نیست.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.letters' => 'رمز عبور باید حداقل یک حرف داشته باشد.',
            'password.numbers' => 'رمز عبور باید حداقل یک عدد داشته باشد.',
            'complex_title.required' => 'نام مجتمع را وارد کنید.',
            'building_title.required' => 'نام ساختمان اولیه را وارد کنید.',
            'province.required' => 'استان را وارد کنید.',
            'city.required' => 'شهر را وارد کنید.',
            'province.in' => 'استان را از فهرست استان‌های ایران انتخاب کنید.',
            'province_id.in' => 'استان انتخاب‌شده معتبر نیست.',
            'province_id.integer' => 'استان انتخاب‌شده معتبر نیست.',
            'province_id.required_with' => 'ابتدا استان را انتخاب کنید.',
            'city_id.integer' => 'شهر انتخاب‌شده معتبر نیست.',
            'city_id.required_with' => 'شهر را انتخاب کنید.',
            'invitation_token.required' => 'برای ثبت‌نام مالک یا ساکن، کد دعوت واحد الزامی است.',
            'invitation_token.min' => 'کد دعوت معتبر نیست.',
            'terms.accepted' => 'پذیرش قوانین و حریم خصوصی الزامی است.',
        ];
    }

    private function cleanText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = mb_strtolower(
            trim((string) $value)
        );

        return $value === '' ? null : $value;
    }

    private function normalizeMobile(mixed $value): string
    {
        $value = strtr(
            trim((string) $value),
            [
                '۰' => '0', '۱' => '1',
                '۲' => '2', '۳' => '3',
                '۴' => '4', '۵' => '5',
                '۶' => '6', '۷' => '7',
                '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1',
                '٢' => '2', '٣' => '3',
                '٤' => '4', '٥' => '5',
                '٦' => '6', '٧' => '7',
                '٨' => '8', '٩' => '9',
            ]
        );

        $value = preg_replace(
            '/[\s\-()]+/',
            '',
            $value
        ) ?? $value;

        if (str_starts_with($value, '+98')) {
            return '0'.substr($value, 3);
        }

        if (str_starts_with($value, '0098')) {
            return '0'.substr($value, 4);
        }

        if (
            str_starts_with($value, '98')
            && strlen($value) === 12
        ) {
            return '0'.substr($value, 2);
        }

        return $value;
    }
}
