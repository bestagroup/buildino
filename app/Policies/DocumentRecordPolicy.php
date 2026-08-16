<?php

namespace App\Policies;

use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentRecordPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'documents';
    }

    public function viewAny(User $user): bool
    {
        return $this->permissions->allowsAnyScope(
            $user,
            $this->permission('view')
        );
    }

    public function create(
        User $user,
        ?Model $target = null
    ): bool {
        $scope = $target === null
            ? null
            : $this->resolveScope($target);

        return $scope !== null
            && $this->permissions->allows(
                $user,
                $this->permission('create'),
                $scope
            );
    }

    protected function resolveScope(Model $model): ?Model
    {
        if (! $model instanceof DocumentRecord) {
            return parent::resolveScope($model);
        }

        $model->loadMissing('documentable');

        return $model->documentable === null
            ? null
            : parent::resolveScope($model->documentable);
    }
}
