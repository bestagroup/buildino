<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'payments';
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->permissions->allows(
            $user,
            'payments.verify',
            $this->resolveScope($payment)
        );
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->permissions->allows(
            $user,
            'payments.refund',
            $this->resolveScope($payment)
        );
    }
}
