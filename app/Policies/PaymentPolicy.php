<?php

namespace App\Policies;

class PaymentPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'payments';
    }
    public function verify(\App\Models\User $user, \App\Models\Payment $payment): bool
    {
        return $this->permissions->allows($user, 'payments.verify', $this->scope($payment));
    }

    public function refund(\App\Models\User $user, \App\Models\Payment $payment): bool
    {
        return $this->permissions->allows($user, 'payments.refund', $this->scope($payment));
    }
}
