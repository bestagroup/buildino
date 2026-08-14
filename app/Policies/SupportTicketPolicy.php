<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SupportTicketPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'support-tickets';
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->is_blocked;
    }

    public function view(User $user, Model $model): bool
    {
        if (
            $model instanceof SupportTicket
            && (int) $model->user_id === (int) $user->getKey()
        ) {
            return true;
        }

        return parent::view($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->is_active && ! $user->is_blocked;
    }

    public function update(User $user, $model): bool
    {
        return parent::update($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return parent::delete($user, $model);
    }
}
