subject = "{{ lang('igniter.voxpilot::branding.mail.invite.subject') }}"
==
{{ lang('igniter.voxpilot::branding.mail.invite.greeting', ['name' => $staff_name]) }}

{{ lang('igniter.voxpilot::branding.mail.invite.intro') }}

{{ lang('igniter.voxpilot::branding.mail.invite.next_step') }}

{{ lang('igniter.voxpilot::branding.mail.invite.button') }}: {{ admin_url('login/reset?code='.$invite_code) }}

{{ lang('igniter.voxpilot::branding.mail.invite.outro') }}
==
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #f8fafb; opacity: 0;">{{ lang('igniter.voxpilot::branding.mail.invite.preheader') }}</div>

<h1 class="vp-title" style="margin: 0 0 16px 0; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 26px; line-height: 1.3; font-weight: 700; color: #0A4174;">{{ lang('igniter.voxpilot::branding.mail.invite.title') }}</h1>

<p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #253745;">{{ lang('igniter.voxpilot::branding.mail.invite.greeting', ['name' => $staff_name]) }}</p>

<p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #253745;">{{ lang('igniter.voxpilot::branding.mail.invite.intro') }}</p>

<p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #253745;">{{ lang('igniter.voxpilot::branding.mail.invite.next_step') }}</p>

@partial('button', ['url' => admin_url('login/reset?code='.$invite_code)])
{{ lang('igniter.voxpilot::branding.mail.invite.button') }}
@endpartial

<p style="margin: 0 0 6px 0; font-size: 13px; line-height: 1.5; color: #5b6b78;">{{ lang('igniter.voxpilot::branding.mail.link_fallback') }}</p>

<p style="margin: 0 0 24px 0; font-size: 13px; line-height: 1.5; word-break: break-all;"><a href="{{ admin_url('login/reset?code='.$invite_code) }}" target="_blank" rel="noopener" style="color: #4E8EA2; text-decoration: underline;">{{ admin_url('login/reset?code='.$invite_code) }}</a></p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 1.6; color: #5b6b78;">{{ lang('igniter.voxpilot::branding.mail.invite.outro') }}</p>

<p style="margin: 24px 0 0 0; padding-top: 16px; border-top: 1px solid #dbe4ea; font-size: 12px; line-height: 1.5; color: #5b6b78;">{{ lang('igniter.voxpilot::branding.mail.invite.footer_note') }}</p>
