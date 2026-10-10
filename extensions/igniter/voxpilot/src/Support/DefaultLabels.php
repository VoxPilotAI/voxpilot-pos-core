<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Support;

/**
 * TastyIgniter seeds statuses, payment methods, staff groups/roles and a customer group with English
 * names that every restaurant shares. In the admin they are shown in the admin's language while they
 * keep their seeded name; a name a restaurant typed itself is shown as it is.
 */
final class DefaultLabels
{
    /** [kind][English seeded value] => key in igniter.voxpilot::defaults */
    private const MAP = [
        'status_order' => ['Received' => 'order_received', 'Pending' => 'order_pending', 'Preparation' => 'order_preparation', 'Delivery' => 'order_delivery', 'Completed' => 'order_completed', 'Canceled' => 'order_canceled'],
        'status_reservation' => ['Confirmed' => 'reservation_confirmed', 'Canceled' => 'reservation_canceled', 'Pending' => 'reservation_pending'],
        'status_comment' => [
            'Your order has been received.' => 'comment_order_received',
            'Your order is pending' => 'comment_order_pending',
            'Your order is in the kitchen' => 'comment_order_preparation',
            'Your order will be with you shortly.' => 'comment_order_delivery',
            'Your table reservation has been confirmed.' => 'comment_reservation_confirmed',
            'Your table reservation has been canceled.' => 'comment_reservation_canceled',
            'Your table reservation is pending.' => 'comment_reservation_pending',
        ],
        'payment_name' => ['Cash On Delivery' => 'payment_cod', 'Stripe Payment' => 'payment_stripe', 'Mollie Payment' => 'payment_mollie', 'Square Payment' => 'payment_square'],
        'payment_description' => [
            'Pay with cash when you pick up your order or when is delivered' => 'payment_desc_cod',
            'Securely pay using your PayPal account' => 'payment_desc_paypal',
            'Pay with your credit card via Authorize.Net' => 'payment_desc_authorize',
            'Pay with your credit card using Stripe' => 'payment_desc_stripe',
            'Pay with your credit card through Mollie' => 'payment_desc_mollie',
            'Pay with your credit card using Square' => 'payment_desc_square',
        ],
        'user_group' => ['Owners' => 'group_owners', 'Managers' => 'group_managers', 'Waiters' => 'group_waiters', 'Delivery' => 'group_delivery'],
        'user_role' => ['Owner' => 'role_owner', 'Manager' => 'role_manager', 'Waiter' => 'role_waiter', 'Delivery' => 'role_delivery'],
        'customer_group' => ['Default group' => 'customer_group_default'],
    ];

    /**
     * The value in the current language when it is a seeded English value, else null (keep it). A
     * list column joins several names with ", " (a staff member's groups): each one is translated.
     */
    public static function translate(string $kind, mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $parts = explode(', ', $value);
        $changed = false;
        foreach ($parts as $i => $part) {
            if ($key = self::MAP[$kind][trim($part)] ?? null) {
                $parts[$i] = lang('igniter.voxpilot::defaults.'.$key);
                $changed = true;
            }
        }

        return $changed ? implode(', ', $parts) : null;
    }
}
