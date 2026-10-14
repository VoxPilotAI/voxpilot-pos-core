<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

use Igniter\Cart\Models\OrderMenu;

/** An order line as the kitchen reads it: the dish with its chosen options ("Pizza Pepperoni · Grande"). */
final class OrderLine
{
    public static function name(OrderMenu $menu): string
    {
        $options = $menu->menu_options->pluck('order_option_name')->filter()->implode(', ');

        return $options !== '' ? $menu->name.' · '.$options : (string) $menu->name;
    }
}
