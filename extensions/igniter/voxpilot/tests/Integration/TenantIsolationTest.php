<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Ingredient;
use Igniter\Cart\Models\Menu;
use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Igniter\User\Models\UserRole;
use Igniter\VoxPilot\Http\Middleware\ResolveTenantForAdmin;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\TenantContext;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Several restaurants share this POS: each one's staff only see and change their own data. */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_menus_categories_and_ingredients_of_another_restaurant_are_invisible(): void
    {
        $mine = $this->makeTenant('Mine');
        $other = $this->makeTenant('Other');
        $myLocation = $this->makeLocation($mine);
        $this->makeMenu($myLocation, 'Pizza Mine');
        $this->makeMenu($this->makeLocation($other), 'Pizza Other');
        $otherIngredient = Ingredient::create(['name' => 'Queso Other', 'status' => 1]);
        $otherIngredient->tenant_id = $other->id;
        $otherIngredient->save();

        app(TenantContext::class)->set($mine);

        $names = Menu::query()->pluck('menu_name')->all();
        $this->assertContains('Pizza Mine', $names);
        $this->assertNotContains('Pizza Other', $names);
        $this->assertNull(Ingredient::find($otherIngredient->getKey()));

        // Made by this restaurant's staff without choosing a location: it is this restaurant's.
        $category = Category::create(['name' => 'Postres '.uniqid(), 'status' => 1]);
        $ingredient = Ingredient::create(['name' => 'Albahaca', 'status' => 1]);
        $this->assertSame([$myLocation->getKey()], $category->locations()->pluck('locations.location_id')->all());
        $this->assertSame($mine->id, (int) $ingredient->tenant_id);
        $this->assertNotNull(Category::find($category->getKey()));

        app(TenantContext::class)->set($other);
        $this->assertNull(Category::find($category->getKey()));
        $this->assertNull(Menu::query()->where('menu_name', 'Pizza Mine')->first());

        $location = Location::create(['location_name' => 'Sucursal 2', 'location_status' => 1]);
        $this->assertSame($other->id, (int) $location->tenant_id);

        // A crafted form cannot put a dish on another restaurant's location.
        $dish = Menu::create(['menu_name' => 'Pizza Crafted', 'menu_price' => 9, 'menu_status' => 1]);
        $dish->locations = [$myLocation->getKey()]; // as the admin form saves the field
        $dish->save();
        $attached = Menu::find($dish->getKey())->locations()->withoutGlobalScopes()->pluck('locations.location_id')->all();
        $this->assertNotContains($myLocation->getKey(), $attached);
        $this->assertContains($location->getKey(), $attached);
    }

    public function test_split_shared_menus_gives_each_restaurant_its_own_copy(): void
    {
        $first = $this->makeTenant('First');
        $second = $this->makeTenant('Second');
        $firstLocation = $this->makeLocation($first);
        $secondLocation = $this->makeLocation($second);
        $shared = $this->makeMenu($firstLocation, 'Pizza Compartida '.uniqid(), 11.99);
        $shared->locations()->attach($secondLocation->getKey());
        (new \Igniter\VoxPilot\Services\MenuAvailability())->setUnavailableToday($secondLocation, $shared->getKey(), true);

        $this->artisan('voxpilot:split-shared-menus')->assertSuccessful();

        $copyId = (int) \DB::table('locationables')->where('locationable_type', 'menus')->where('location_id', $secondLocation->getKey())->value('locationable_id');
        $this->assertNotSame($shared->getKey(), $copyId);
        $this->assertSame([$firstLocation->getKey()], \DB::table('locationables')->where('locationable_type', 'menus')->where('locationable_id', $shared->getKey())->pluck('location_id')->map(fn ($id) => (int) $id)->all());
        $copy = Menu::withoutGlobalScopes()->find($copyId);
        $this->assertSame($shared->menu_name, $copy->menu_name);
        $this->assertSame(11.99, (float) $copy->menu_price);
        $this->assertSame([$copyId], (new \Igniter\VoxPilot\Services\MenuAvailability())->unavailableToday($secondLocation->fresh()));
    }

    public function test_staff_see_only_their_own_team(): void
    {
        $mine = $this->makeTenant('Mine');
        $other = $this->makeTenant('Other');
        $me = $this->makeStaff($mine);
        $stranger = $this->makeStaff($other);

        app(TenantContext::class)->set($mine);

        $this->assertNotNull(User::find($me->getKey()));
        $this->assertNull(User::find($stranger->getKey()));
    }

    public function test_restaurant_staff_lose_the_platform_permissions_and_super_users_keep_them(): void
    {
        $role = UserRole::create(['name' => 'Gerente '.uniqid(), 'code' => 'gerente-'.uniqid(), 'permissions' => [
            'Admin.Orders' => 1, 'Admin.Menus' => 1, 'Admin.Statuses' => 1, 'Site.Settings' => 1, 'Admin.Payments' => 1,
        ]]);
        $staff = $this->makeStaff($this->makeTenant(), $role);

        $this->runAdminMiddlewareAs($staff);

        $this->assertTrue($staff->hasPermission('Admin.Orders'));
        $this->assertTrue($staff->hasPermission('Admin.Menus'));
        $this->assertFalse($staff->hasPermission('Admin.Statuses'));
        $this->assertFalse($staff->hasPermission('Site.Settings'));
        $this->assertFalse($staff->hasPermission('Admin.Payments'));
        // The shared role itself is untouched.
        $this->assertArrayHasKey('Admin.Statuses', UserRole::find($role->getKey())->permissions);
        $this->assertFalse($staff->role->save());

        // Staff of no restaurant keep no permission at all.
        app(TenantContext::class)->clear();
        Auth::guard('igniter-admin')->logout();
        $loner = $this->makeStaff(null, $role);
        $this->runAdminMiddlewareAs($loner);
        $this->assertFalse($loner->hasPermission('Admin.Orders'));
    }

    protected function makeStaff($tenant, ?UserRole $role = null): User
    {
        $user = User::create([
            'name' => 'Staff '.uniqid(),
            'email' => uniqid('staff').'@example.test',
            'username' => uniqid('staff'),
            'password' => 'Secret123!x',
            'status' => true,
        ]);
        $user->user_role_id = $role?->getKey();
        $user->super_user = false;
        $user->save();
        if ($tenant) {
            TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->user_id, 'role' => 'staff']);
        }

        return $user->fresh();
    }

    protected function runAdminMiddlewareAs(User $user): void
    {
        Auth::guard('igniter-admin')->setUser($user);
        app(ResolveTenantForAdmin::class)->handle(Request::create('/admin'), fn () => null);
    }
}
