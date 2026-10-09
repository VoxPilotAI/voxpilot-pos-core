<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

/**
 * Maps a language tag from VoxPilot or a browser ("es-CR", "pt_BR", "DE") to one of the languages
 * this extension has copy for, or English.
 */
final class Locale
{
    public static function supported(): array
    {
        return (array) config('voxpilot.locales', ['en']);
    }

    public static function normalize(?string $tag): string
    {
        $base = strtolower(substr(trim((string) $tag), 0, 2));

        return in_array($base, self::supported(), true) ? $base : 'en';
    }
}
