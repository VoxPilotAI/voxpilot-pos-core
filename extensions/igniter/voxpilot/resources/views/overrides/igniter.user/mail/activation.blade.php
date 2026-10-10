subject = "{{ lang('igniter.voxpilot::mail.activation.subject') }}"
==
{{ lang('igniter.voxpilot::mail.activation.l2') }}

{{ lang('igniter.voxpilot::mail.activation.l4') }}

{{ lang('igniter.voxpilot::mail.activation.l6') }}

{{ lang('igniter.voxpilot::mail.activation.l8') }}
==
{{ lang('igniter.voxpilot::mail.activation.l10') }}

{{ lang('igniter.voxpilot::mail.activation.l12') }}

{{ lang('igniter.voxpilot::mail.activation.l14') }}

@partial('button', ['url' => '{account_activation_link}', 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.activation.l17') }}
@endpartial

{!! lang('igniter.voxpilot::mail.activation.l20') !!}
{{ lang('igniter.voxpilot::mail.activation.l21') }}
