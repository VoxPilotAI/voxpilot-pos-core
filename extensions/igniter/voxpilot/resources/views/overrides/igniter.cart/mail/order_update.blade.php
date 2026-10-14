subject = "{{ lang('igniter.voxpilot::mail.order_update.subject', ['order_number' => $order_number]) }}"
==
{{ lang('igniter.voxpilot::mail.order_update.l2') }}

{{ lang('igniter.voxpilot::mail.order_update.l4', ['order_number' => $order_number, 'status_name' => $status_name]) }}

{{ lang('igniter.voxpilot::mail.order_update.l6') }}
{{ $status_comment }}

{{ lang('igniter.voxpilot::mail.order_update.l9') }}
{{ $order_view_url }}
==
{{ lang('igniter.voxpilot::mail.order_update.l12', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{!! lang('igniter.voxpilot::mail.order_update.l14', ['order_number' => e($order_number)]) !!}
**{{ $status_name }}**

{!! lang('igniter.voxpilot::mail.order_update.l17') !!}
**{{ $status_comment }}**

@partial('button', ['url' => $order_view_url, 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.order_update.l21') }}
@endpartial
