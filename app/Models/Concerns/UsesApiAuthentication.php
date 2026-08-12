<?php

namespace App\Models\Concerns;

use Laravel\Sanctum\HasApiTokens;

trait UsesApiAuthentication
{
    use HasApiTokens;
}
