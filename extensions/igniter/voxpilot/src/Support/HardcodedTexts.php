<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

/**
 * English texts TastyIgniter hard-codes in its scripts and views (dialogs, the date range picker,
 * screen-reader labels such as "Close") => the current language. The admin swaps them in the
 * browser (vp-admin.js); storefront pages get them swapped in the HTML (StorefrontLanding).
 */
final class HardcodedTexts
{
    public static function all(): array
    {
        $ui = fn (string $key) => lang('igniter.voxpilot::ui.'.$key);
        $texts = [
            'Close' => lang('igniter::admin.button_close'),
            'Cancel' => lang('igniter::main.media_manager.button_cancel'),
            'Apply' => lang('igniter::admin.text_apply'),
            'Today' => lang('igniter::system.date.today'),
            'Yesterday' => lang('igniter::system.date.yesterday'),
            'Last 7 Days' => lang('igniter::admin.dashboard.text_week'),
            'Last 30 Days' => lang('igniter::admin.dashboard.text_month'),
            'This Month' => $ui('this_month'),
            'Last Month' => $ui('last_month'),
            'Lifetime' => $ui('lifetime'),
            'Custom Range' => $ui('custom_range'),
            'Are you sure you want to do this?' => lang('igniter::admin.alert_confirm'),
            'Please select a single item.' => $ui('select_single'),
            'Please select image(s) to insert.' => $ui('select_images'),
            'Are you sure you want to cancel editing this translation?' => $ui('cancel_translation'),
            'Map is missing center coordinates, please enter an address then click save.' => $ui('map_center'),
            'Missing Google Maps Javascript Library, please provide your maps api key on the general system settings page.' => $ui('map_library'),
            'Switch light and dark mode' => $ui('theme_toggle'),
            'Previous' => $ui('previous'),
            'Next' => $ui('next'),
            'Toggle Dropdown' => $ui('toggle_dropdown'),
            'Loading...' => $ui('loading'),
            'Toggle navigation' => $ui('toggle_navigation'),
            'Remove' => $ui('remove'),
            'Sort' => $ui('sort'),
            'Filter' => $ui('filter'),
            'Choose your color' => $ui('choose_color'),
            'Resize' => $ui('resize'),
        ];

        return array_filter($texts, fn ($text, $english) => $text !== $english, ARRAY_FILTER_USE_BOTH);
    }

    /** Choices.js texts of TastyIgniter's select lists (":value" / ":count" filled in by vp-admin.js). */
    public static function selectListTexts(): array
    {
        $keys = ['no_results', 'no_choices', 'select', 'unique', 'custom_add', 'add', 'max', 'remove'];

        return array_combine($keys, array_map(fn ($key) => lang('igniter.voxpilot::ui.choices_'.$key), $keys));
    }

    /**
     * The HTML with those texts translated where they are attributes (aria-label, title,
     * placeholder) or screen-reader-only text.
     */
    public static function translateHtml(string $html): string
    {
        $texts = self::all();
        if (!$texts) {
            return $html;
        }
        $quoted = implode('|', array_map(fn ($text) => preg_quote(e($text), '/'), array_keys($texts)));
        $lookup = [];
        foreach ($texts as $english => $translated) {
            $lookup[e($english)] = e($translated);
        }
        $html = preg_replace_callback('/\b(aria-label|title|placeholder)="('.$quoted.')"/', fn ($m) => $m[1].'="'.$lookup[$m[2]].'"', $html);

        return preg_replace_callback('/(<span class="(?:visually-hidden|sr-only)[^"]*">)('.$quoted.')(<\/span>)/', fn ($m) => $m[1].$lookup[$m[2]].$m[3], $html);
    }
}
