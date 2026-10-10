<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ingredients (and allergens) belong to a restaurant: disabling one makes that restaurant's dishes
 * unavailable, so a shared row let one restaurant switch off another's dishes. Existing rows go to
 * the restaurant whose menus use them when there is exactly one; the rest stay platform rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('ingredient_id');
        });

        $p = DB::getTablePrefix(); // table aliases are prefixed too, raw expressions are not
        $owners = DB::table('ingredientables as mi')
            ->whereIn('mi.ingredientable_type', ['menus', 'Igniter\\Cart\\Models\\Menu'])
            ->join('locationables as l', function ($join): void {
                $join->on('l.locationable_id', '=', 'mi.ingredientable_id')->whereIn('l.locationable_type', ['menus', 'Igniter\\Cart\\Models\\Menu']);
            })
            ->join('locations as loc', 'loc.location_id', '=', 'l.location_id')
            ->whereNotNull('loc.tenant_id')
            ->groupBy('mi.ingredient_id')
            ->havingRaw("COUNT(DISTINCT {$p}loc.tenant_id) = 1")
            ->select('mi.ingredient_id')
            ->selectRaw("MIN({$p}loc.tenant_id) as tenant_id")
            ->get();

        foreach ($owners as $owner) {
            DB::table('ingredients')->where('ingredient_id', $owner->ingredient_id)->update(['tenant_id' => $owner->tenant_id]);
        }
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
