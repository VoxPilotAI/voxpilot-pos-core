subject = "{{ lang('igniter.voxpilot::mail.reservation_reminder.subject', ['reservation_number' => $reservation_number]) }}"
==
{{ lang('igniter.voxpilot::mail.reservation_reminder.l2') }}

{{ lang('igniter.voxpilot::mail.reservation_reminder.l4', ['reservation_number' => $reservation_number, 'location_name' => $location_name, 'status_name' => $status_name]) }}

{{ lang('igniter.voxpilot::mail.reservation_reminder.l6') }}
{{ $status_comment }}

{{ lang('igniter.voxpilot::mail.reservation_reminder.l9') }}
{{ $reservation_view_url }}
==
{{ lang('igniter.voxpilot::mail.reservation_reminder.l12', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.reservation_reminder.l14', ['reservation_number' => $reservation_number, 'location_name' => $location_name, 'status_name' => $status_name]) }}
<br>
**{{ $status_name }}**

{!! lang('igniter.voxpilot::mail.reservation_reminder.l18') !!}
{{ $status_comment }}

@partial('button', ['url' => $reservation_view_url, 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.reservation_reminder.l22') }}
@endpartial
