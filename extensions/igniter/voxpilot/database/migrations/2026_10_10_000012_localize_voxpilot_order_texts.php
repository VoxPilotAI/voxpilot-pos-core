<?php

declare(strict_types=1);

use Igniter\VoxPilot\Support\Locale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orders VoxPilot created before the POS wrote them in the restaurant's language kept English
 * texts: the totals ("Sub Total", "Delivery", "Order Total"), the English outcome summary
 * ("Confirmed pickup order (2 items)") and the phone-change note. They are rewritten in the
 * restaurant's language so the kitchen never reads a mix of languages.
 */
return new class extends Migration
{
    private const TOTALS = [
        'subtotal' => ['Sub Total', 'igniter.cart::default.text_sub_total'],
        'delivery' => ['Delivery', 'igniter.cart::default.orders.text_delivery'],
        'total' => ['Order Total', 'igniter.cart::default.text_order_total'],
    ];

    public function up(): void
    {
        $tenants = DB::table('voxpilot_tenants')->get(['id', 'settings']);
        foreach ($tenants as $tenant) {
            $locale = Locale::match(((array) json_decode((string) $tenant->settings, true))['locale'] ?? null);
            if (!$locale || $locale === 'en') {
                continue;
            }
            $orderIds = DB::table('voxpilot_order_metadata')->where('tenant_id', $tenant->id)->pluck('order_id');
            foreach ($orderIds->chunk(500) as $chunk) {
                foreach (self::TOTALS as $code => [$english, $key]) {
                    DB::table('order_totals')->whereIn('order_id', $chunk)->where('code', $code)->where('title', $english)
                        ->update(['title' => trans($key, [], $locale)]);
                }
                $changed = trans('igniter.voxpilot::orders.changed_by_phone', [], $locale);
                foreach (DB::table('orders')->whereIn('order_id', $chunk)->get(['order_id', 'comment']) as $order) {
                    $lines = preg_split('/\R/', (string) $order->comment);
                    $kept = [];
                    foreach ($lines as $line) {
                        if (preg_match('/^Confirmed (pickup|delivery|unknown) order \(\d+ items\)$/', trim($line))) {
                            continue;
                        }
                        $kept[] = trim($line) === '✏ Changed by the customer on the phone' ? $changed : $line;
                    }
                    $comment = trim(implode("\n", $kept));
                    if ($comment !== trim((string) $order->comment)) {
                        DB::table('orders')->where('order_id', $order->order_id)->update(['comment' => $comment === '' ? null : $comment]);
                    }
                }
            }
        }
    }

    public function down(): void {}
};
