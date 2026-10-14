<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Scopes;

use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A restaurant's staff see only their own team (its memberships), never other restaurants' staff
 * or the platform's super users. Not applied before the admin's restaurant is known, so signing in
 * and restoring the session are unaffected.
 */
class TenantStaffScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        if (!$context->isActive()) {
            return;
        }

        $builder->whereIn(
            $model->getTable().'.user_id',
            TenantMembership::query()->select('user_id')->where('tenant_id', $context->id())
        );
    }
}
