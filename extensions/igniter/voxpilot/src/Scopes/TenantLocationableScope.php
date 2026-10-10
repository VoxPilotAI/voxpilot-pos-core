<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Scopes;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restaurant data that TastyIgniter ties to locations (menus, categories, options, mealtimes,
 * coupons, stock, reviews, delivery areas…): a restaurant's staff only see what belongs to their own
 * locations. TastyIgniter's own location filter is not enough for several restaurants: it applies
 * only to staff with assigned locations and shows records without a location to everyone.
 */
class TenantLocationableScope implements Scope
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

        $tenantLocations = Location::withoutGlobalScopes()->select('location_id')->where('tenant_id', $context->id());

        if (method_exists($model, 'hasRelation') && $model->hasRelation('locations')) {
            // Many-to-many (locationables): the record is attached to at least one of the restaurant's locations.
            $builder->whereHas('locations', fn (Builder $query) => $query->whereIn('locations.location_id', $tenantLocations));

            return;
        }

        $builder->whereIn($model->getTable().'.location_id', $tenantLocations);
    }
}
