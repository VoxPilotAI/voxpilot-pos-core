<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

use Igniter\System\Models\Language;

/**
 * Maps a language tag from VoxPilot or a browser ("es-CR", "pt_BR", "DE") to one of the languages
 * this extension has copy for, or English.
 */
final class Locale
{
    /** Names shown in the TastyIgniter languages list, in their own language. */
    private const NATIVE_NAMES = [
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'it' => 'Italiano',
        'pt' => 'Português',
        'nl' => 'Nederlands',
    ];

    public static function supported(): array
    {
        return (array) config('voxpilot.locales', ['en']);
    }

    public static function normalize(?string $tag): string
    {
        return self::match($tag) ?? 'en';
    }

    /** The supported language for a tag, or null when there is none. */
    public static function match(?string $tag): ?string
    {
        $base = strtolower(substr(trim((string) $tag), 0, 2));

        return in_array($base, self::supported(), true) ? $base : null;
    }

    /**
     * The enabled TastyIgniter language for a supported code, created when missing. TastyIgniter only
     * applies a staff member's language (admin locale) when it is an enabled language.
     */
    public static function language(string $code): Language
    {
        $language = Language::where('code', $code)->first()
            ?? Language::create(['code' => $code, 'name' => self::NATIVE_NAMES[$code] ?? $code, 'status' => true]);

        if (!$language->status) {
            $language->status = true;
            $language->save();
        }

        return $language;
    }
}
