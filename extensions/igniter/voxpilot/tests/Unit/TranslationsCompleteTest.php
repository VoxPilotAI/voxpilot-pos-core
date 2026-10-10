<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Support\DefaultLabels;
use Tests\TestCase;

/**
 * Every screen and email of the POS reads the same in each language the admin can choose: no
 * key may exist in English only (that is how a Spanish screen ended up half in English).
 */
class TranslationsCompleteTest extends TestCase
{
    private const LOCALES = ['es', 'de', 'fr', 'it', 'pt', 'nl'];

    private static function keys(array $lines, string $prefix = ''): array
    {
        $keys = [];
        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = is_array($value) ? array_merge($keys, self::keys($value, $path)) : array_merge($keys, [$path]);
        }

        return $keys;
    }

    public function test_every_voxpilot_string_exists_in_every_language(): void
    {
        $dir = __DIR__.'/../../resources/lang';
        foreach (glob($dir.'/en/*.php') as $file) {
            $english = self::keys(require $file);
            foreach (self::LOCALES as $locale) {
                $path = $dir.'/'.$locale.'/'.basename($file);
                $this->assertFileExists($path);
                $missing = array_diff($english, self::keys(require $path));
                $this->assertSame([], array_values($missing), "$locale/".basename($file).' misses keys');
            }
        }
    }

    public function test_tastyigniter_strings_are_translated_for_every_language(): void
    {
        foreach (self::LOCALES as $locale) {
            app()->setLocale($locale);
            foreach (['igniter::admin.text_enabled', 'igniter.cart::default.text_order_total', 'igniter.user::default.text_title', 'validation.required', 'Edit'] as $key) {
                $english = trans($key, [], 'en');
                $this->assertNotSame($english, trans($key), "$locale: $key is still English");
            }
        }
    }

    public function test_seeded_english_names_are_shown_translated_and_custom_names_are_kept(): void
    {
        app()->setLocale('es');

        $this->assertSame('Recibido', DefaultLabels::translate('status_order', 'Received'));
        $this->assertSame('Confirmada', DefaultLabels::translate('status_reservation', 'Confirmed'));
        $this->assertSame('Dueños, Repartidores', DefaultLabels::translate('user_group', 'Owners, Delivery'));
        $this->assertSame('Pago contra entrega', DefaultLabels::translate('payment_name', 'Cash On Delivery'));
        $this->assertNull(DefaultLabels::translate('status_order', 'Listo en barra'));
        $this->assertNull(DefaultLabels::translate('payment_name', 'PayPal Express'));
    }
}
