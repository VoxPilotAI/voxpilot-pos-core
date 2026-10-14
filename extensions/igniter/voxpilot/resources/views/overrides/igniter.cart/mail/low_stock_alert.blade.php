subject = "{{ lang('igniter.voxpilot::mail.low_stock_alert.subject', ['stock_name' => $stock_name]) }}"
==
{{ lang('igniter.voxpilot::mail.low_stock_alert.l2', ['stock_name' => $stock_name]) }}

{!! lang('igniter.voxpilot::mail.low_stock_alert.l4', ['location_name' => e($location_name)]) !!}
{!! lang('igniter.voxpilot::mail.low_stock_alert.l5', ['quantity' => e($quantity)]) !!}
{{ lang('igniter.voxpilot::mail.low_stock_alert.l6', ['low_stock_threshold' => $low_stock_threshold]) }}

==
{{ lang('igniter.voxpilot::mail.low_stock_alert.l9', ['stock_name' => $stock_name]) }}

{!! lang('igniter.voxpilot::mail.low_stock_alert.l11', ['location_name' => e($location_name)]) !!}
{!! lang('igniter.voxpilot::mail.low_stock_alert.l12', ['quantity' => e($quantity)]) !!}
{{ lang('igniter.voxpilot::mail.low_stock_alert.l13', ['low_stock_threshold' => $low_stock_threshold]) }}
