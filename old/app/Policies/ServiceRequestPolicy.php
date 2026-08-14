<?php

namespace App\Policies;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ServiceRequestPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'service-requests';
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && ! $user->is_blocked;
    }

    public function view(User $user, Model $model): bool
    {
        if (
            $model instanceof ServiceRequest
            && (
                (int) $model->requested_by === (int) $user->getKey()
                || (int) $model->assigned_to === (int) $user->getKey()
            )
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
        if (
            $model instanceof ServiceRequest
            && (int) $model->requested_by === (int) $user->getKey()
            && $model->status === ServiceRequestStatus::Open
            && $model->assigned_to === null
        ) {
            return true;
        }

        return parent::update($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        if (
            $model instanceof ServiceRequest
            && (int) $model->requested_by === (int) $user->getKey()
            && $model->status === ServiceRequestStatus::Open
            && $model->assigned_to === null
        ) {
            return true;
        }

        return parent::delete($user, $model);
    }
}
