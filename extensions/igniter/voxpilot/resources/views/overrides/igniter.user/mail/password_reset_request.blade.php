subject = "{{ lang('igniter.voxpilot::mail.password_reset_request.subject') }}"
==
{{ lang('igniter.voxpilot::mail.password_reset_request.l2') }}

{{ lang('igniter.voxpilot::mail.password_reset_request.l4') }}

{{ lang('igniter.voxpilot::mail.password_reset_request.l6') }}
==
{{ lang('igniter.voxpilot::mail.password_reset_request.l8') }}

{{ lang('igniter.voxpilot::mail.password_reset_request.l10') }}

{{ lang('igniter.voxpilot::mail.password_reset_request.l12') }}

@partial('button', ['url' => '{reset_link}', 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.password_reset_request.l15') }}
@endpartial

{!! lang('igniter.voxpilot::mail.password_reset_request.l18') !!}
{{ lang('igniter.voxpilot::mail.password_reset_request.l19') }}
