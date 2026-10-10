<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Scopes;

use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Customer accounts are shared by the storefront: a restaurant sees those who ordered from it. */
class TenantCustomerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->deniesAll()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($context->isActive()) {
            // The orders relation carries the tenant order scope.
            $builder->whereHas('orders');
        }
    }
}
