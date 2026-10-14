<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Scopes;

use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantLocationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->deniesAll()) {
            // Fail closed: an admin who belongs to no restaurant sees no restaurant's data.
            $builder->whereRaw('1 = 0');

            return;
        }

        if (!$context->isActive()) {
            return;
        }

        $builder->where($model->getTable().'.tenant_id', $context->id());
    }
}
