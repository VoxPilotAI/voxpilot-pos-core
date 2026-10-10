<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\User\Models\User;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Services\LanguagePreference;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Admin language: per staff member, and the restaurant's language set by the owner for the team. */
class LanguagePreferenceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected function member($tenant, string $role, ?int $languageId = null): User
    {
        $user = User::create(['name' => 'Staff '.uniqid(), 'email' => uniqid().'@lang.test', 'username' => uniqid('u'), 'status' => true]);
        if ($languageId) {
            $user->language_id = $languageId;
            $user->save();
        }
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->user_id, 'role' => $role]);

        return $user->fresh();
    }

    public function test_a_staff_member_changes_only_their_own_language(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->member($tenant, 'owner');
        $cook = $this->member($tenant, 'staff');

        (new LanguagePreference())->setForUser($cook, 'de');

        $this->assertSame('de', $cook->fresh()->getLocale());
        $this->assertNotSame('de', $owner->fresh()->getLocale());
    }

    public function test_the_owner_sets_the_language_of_the_whole_restaurant_only(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->member($tenant, 'owner');
        $cook = $this->member($tenant, 'staff');
        $stranger = $this->member($this->makeTenant('Other'), 'owner');
        $preference = new LanguagePreference();

        $this->assertTrue($preference->canSetForTenant($owner, $tenant));
        $this->assertFalse($preference->canSetForTenant($cook, $tenant));
        $this->assertFalse($preference->canSetForTenant($stranger, $tenant));

        $preference->setForTenant($tenant, 'fr');

        $this->assertSame('fr', $preference->tenantLocale($tenant->fresh()));
        $this->assertSame('fr', $owner->fresh()->getLocale());
        $this->assertSame('fr', $cook->fresh()->getLocale());
        $this->assertNotSame('fr', $stranger->fresh()->getLocale());
    }

    public function test_new_staff_start_in_the_restaurants_language(): void
    {
        $tenant = $this->makeTenant();
        (new LanguagePreference())->setForTenant($tenant, 'it');

        $newcomer = $this->member($tenant->fresh(), 'staff');

        $this->assertSame('it', $newcomer->getLocale());
    }
}
