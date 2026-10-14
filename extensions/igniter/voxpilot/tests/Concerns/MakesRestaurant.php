<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Concerns;

use Igniter\Cart\Models\Menu;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\Tenant;

/** A tenant with its own location and menu items, inside the test's transaction. */
trait MakesRestaurant
{
    protected function makeTenant(string $name = 'Ops Pizzeria'): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => 'ops-'.uniqid(),
            'external_tenant_id' => 'vp_ops_'.uniqid(),
            'status' => 'active',
        ]);
    }

    protected function makeLocation(Tenant $tenant): Location
    {
        $location = Location::withoutGlobalScopes()->create([
            'location_name' => 'Ops '.uniqid(),
            'location_status' => 1,
        ]);
        $location->tenant_id = $tenant->id;
        $location->save();

        return $location;
    }

    protected function makeMenu(Location $location, string $name, float $price = 10.0): Menu
    {
        $menu = Menu::create(['menu_name' => $name, 'menu_price' => $price, 'menu_status' => 1]);
        $menu->locations()->attach($location->getKey());

        return $menu;
    }
}
