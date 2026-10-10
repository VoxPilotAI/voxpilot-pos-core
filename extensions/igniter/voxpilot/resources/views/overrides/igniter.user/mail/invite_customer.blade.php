subject = "{{ lang('igniter.voxpilot::mail.invite_customer.subject', ['site_name' => $site_name]) }}"
==
{{ lang('igniter.voxpilot::mail.invite_customer.l2', ['full_name' => $full_name, 'site_name' => $site_name]) }}

{{ lang('igniter.voxpilot::mail.invite_customer.l4', ['site_name' => $site_name]) }}

{{ page_url('account.reset').'/'.$invite_code }}
==
{{ lang('igniter.voxpilot::mail.invite_customer.l8', ['full_name' => $full_name, 'site_name' => $site_name]) }}

{{ lang('igniter.voxpilot::mail.invite_customer.l10', ['site_name' => $site_name]) }}

@partial('button', ['url' => page_url('account.reset').'/'.$invite_code, 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.invite_customer.l13') }}
@endpartial
