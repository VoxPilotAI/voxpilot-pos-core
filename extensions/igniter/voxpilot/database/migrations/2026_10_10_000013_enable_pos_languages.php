<?php

declare(strict_types=1);

use Igniter\System\Models\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The POS speaks the languages its strings are translated into (lang/<locale>): they are enabled
 * TastyIgniter languages, and storefront pages follow the visitor's browser language (as the
 * landing page does) instead of showing everyone the English default.
 */
return new class extends Migration
{
    private const LANGUAGES = [
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'it' => 'Italiano',
        'pt' => 'Português',
        'nl' => 'Nederlands',
    ];

    public function up(): void
    {
        foreach (self::LANGUAGES as $code => $name) {
            $exists = DB::table('languages')->where('code', $code)->exists();
            if ($exists) {
                DB::table('languages')->where('code', $code)->update(['name' => $name, 'status' => 1]);
            } else {
                DB::table('languages')->insert([
                    'code' => $code, 'name' => $name, 'idiom' => $code, 'status' => 1, 'can_delete' => 0,
                    'is_default' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        // A marketplace language pack that never installed (no carte key) left an empty es_ES.
        DB::table('languages')->where('code', 'es_ES')->where('name', 'Spanish')->delete();

        Settings::set('supported_languages', array_keys(self::LANGUAGES), 'prefs');
        Settings::set('detect_language', 1);
    }

    public function down(): void {}
};
