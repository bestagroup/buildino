<?php

namespace App\Services\Web;

use App\Support\Input\DigitNormalizer;
use Illuminate\Validation\ValidationException;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

final class BuildingWorkbookDate
{
    public function display(string $date): string
    {
        return $date === '' ? '' : Jalalian::fromDateTime($date)->format('Y/m/d');
    }

    public function storage(string $date): string
    {
        $date = app(DigitNormalizer::class)->normalize(trim($date));
        if ($date === '') {
            return '';
        }
        if (preg_match('~^(1[2-6][0-9]{2})[/-]([0-9]{1,2})[/-]([0-9]{1,2})$~', $date, $parts)
            && CalendarUtils::checkDate((int) $parts[1], (int) $parts[2], (int) $parts[3])) {
            return (new Jalalian((int) $parts[1], (int) $parts[2], (int) $parts[3]))->toCarbon()->toDateString();
        }
        // Preserve compatibility with previously issued Gregorian workbooks.
        if (preg_match('/^(?:19|20)[0-9]{2}-[0-9]{2}-[0-9]{2}$/', $date)
            && validator(['date' => $date], ['date' => 'date_format:Y-m-d'])->passes()) {
            return $date;
        }
        throw ValidationException::withMessages(['file' => 'تاریخ مالکیت و سکونت باید تاریخ شمسی معتبر مانند ۱۴۰۵/۰۷/۰۱ باشد.']);
    }
}
