<?php

namespace App\Policies;

use App\Models\DocumentRecord;
use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FilePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'files';
    }

    public function create(
        User $user,
        ?Model $related = null
    ): bool {
        $scope = $related === null
            ? null
            : $this->relatedScope($related);

        return $scope !== null
            && $this->permissions->allows(
                $user,
                $this->permission('create'),
                $scope
            );
    }

    public function view(User $user, Model $model): bool
    {
        return $model instanceof File
            && $this->allowsForAnyRelation(
                $user,
                $model,
                $this->permission('view')
            );
    }

    public function update(User $user, Model $model): bool
    {
        return $model instanceof File
            && $this->allowsForAnyRelation(
                $user,
                $model,
                $this->permission('update')
            );
    }

    public function delete(User $user, Model $model): bool
    {
        return $model instanceof File
            && $this->allowsForAnyRelation(
                $user,
                $model,
                $this->permission('delete')
            );
    }

    private function allowsForAnyRelation(
        User $user,
        File $file,
        string $permission
    ): bool {
        $file->loadMissing('fileRelations.related');

        return $file->fileRelations->contains(
            function ($relation) use (
                $permission,
                $user
            ): bool {
                $scope = $relation->related === null
                    ? null
                    : $this->relatedScope(
                        $relation->related
                    );

                return $scope !== null
                    && $this->permissions->allows(
                        $user,
                        $permission,
                        $scope
                    );
            }
        );
    }

    private function relatedScope(Model $related): ?Model
    {
        if ($related instanceof DocumentRecord) {
            $related->loadMissing('documentable');

            return $related->documentable === null
                ? null
                : parent::resolveScope(
                    $related->documentable
                );
        }

        return parent::resolveScope($related);
    }
}
