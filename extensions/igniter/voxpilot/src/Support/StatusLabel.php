<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

/** TastyIgniter's default order status names in the admin's language; custom names as they are. */
final class StatusLabel
{
    public static function for(?string $name): string
    {
        $key = 'igniter.voxpilot::orders.status_'.strtolower(str_replace(' ', '_', (string) $name));

        return lang($key) !== $key ? lang($key) : (string) $name;
    }
}
