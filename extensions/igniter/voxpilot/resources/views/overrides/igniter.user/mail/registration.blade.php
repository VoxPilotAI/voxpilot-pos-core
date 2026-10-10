subject = "{{ lang('igniter.voxpilot::mail.registration.subject', ['site_name' => $site_name]) }}"
==
{{ lang('igniter.voxpilot::mail.registration.l2', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.registration.l4', ['site_name' => $site_name]) }}

{{ lang('igniter.voxpilot::mail.registration.l6', ['account_login_link' => $account_login_link]) }}
==
{{ lang('igniter.voxpilot::mail.registration.l8', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.registration.l10', ['site_name' => $site_name]) }}

{{ lang('igniter.voxpilot::mail.registration.l12') }}

@partial('button', ['url' => $account_login_link, 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.registration.l15') }}
@endpartial
