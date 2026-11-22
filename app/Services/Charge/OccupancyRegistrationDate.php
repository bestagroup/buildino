<?php

namespace App\Services\Charge;

use Carbon\CarbonInterface;
use Morilog\Jalali\Jalalian;

final class OccupancyRegistrationDate
{
    public function monthStart(CarbonInterface $registeredAt): string
    {
        $date = Jalalian::fromDateTime($registeredAt->copy()->setTimezone('Asia/Tehran'));

        return (new Jalalian($date->getYear(), $date->getMonth(), 1))->toCarbon()->toDateString();
    }
}
