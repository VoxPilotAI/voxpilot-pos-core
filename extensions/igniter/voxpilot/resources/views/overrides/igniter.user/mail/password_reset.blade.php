subject = "{{ lang('igniter.voxpilot::mail.password_reset.subject', ['site_name' => $site_name]) }}"
==
{{ lang('igniter.voxpilot::mail.password_reset.l2', ['full_name' => $full_name]) }}

{{ lang('igniter.voxpilot::mail.password_reset.l4') }}

@isset($account_login_link)
    {{ lang('igniter.voxpilot::mail.password_reset.l7') }}
    {{$account_login_link}}
@endisset

{{ lang('igniter.voxpilot::mail.password_reset.l11') }}
==
{{ lang('igniter.voxpilot::mail.password_reset.l13', ['first_name' => $first_name, 'last_name' => $last_name]) }}

{{ lang('igniter.voxpilot::mail.password_reset.l15') }}

@isset($account_login_link)
@partial('button', ['url' => $account_login_link, 'type' => 'primary'])
{{ lang('igniter.voxpilot::mail.password_reset.l19') }}
@endpartial
@endisset

{{ lang('igniter.voxpilot::mail.password_reset.l23') }}
