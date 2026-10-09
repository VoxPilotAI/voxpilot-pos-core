name = "VoxPilot POS layout"
==
{!! $body !!}

--
{{ lang('igniter.voxpilot::branding.mail.company_line') }}
{{ lang('igniter.voxpilot::branding.mail.support_line', ['email' => config('voxpilot.support_email')]) }}
==
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8"/>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="x-apple-disable-message-reformatting"/>
    <meta name="color-scheme" content="light"/>
    <meta name="supported-color-schemes" content="light"/>
    <title>{{ $site_name ?? config('voxpilot.brand_name') }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style type="text/css">
        :root { color-scheme: light; supported-color-schemes: light; }
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        .vp-body p { margin: 0 0 16px 0; }
        .vp-body a { color: #4E8EA2; }
        @media only screen and (max-width: 620px) {
            .vp-container { width: 100% !important; max-width: 100% !important; }
            .vp-content { padding: 28px 20px !important; }
            .vp-btn a { display: block !important; width: 100% !important; box-sizing: border-box !important; }
            .vp-title { font-size: 22px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafb; color: #253745; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" bgcolor="#f8fafb" style="background-color: #f8fafb;">
    <tr>
        <td align="center" style="padding: 32px 16px;">
            <!--[if mso]><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"><tr><td><![endif]-->
            <table role="presentation" class="vp-container" cellpadding="0" cellspacing="0" border="0" width="100%" style="width: 100%; max-width: 600px; margin: 0 auto;">
                <tr>
                    <td align="left" style="padding: 8px 8px 20px 8px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td valign="middle" style="padding: 0;">
                                    <img src="{{ asset('voxpilot/email-logo.png') }}" width="180" height="24" alt="VoxPilot" style="display: block; width: 180px; height: auto; max-width: 180px; border: 0; font-family: Inter, Arial, sans-serif; font-size: 22px; font-weight: 800; color: #0A4174;"/>
                                </td>
                                <td valign="middle" style="padding: 0 0 0 10px;">
                                    <span style="display: inline-block; padding: 3px 9px; border: 1px solid #dbe4ea; border-radius: 999px; font-family: Inter, Arial, sans-serif; font-size: 11px; line-height: 16px; font-weight: 700; letter-spacing: 1.5px; color: #4E8EA2;">POS</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td bgcolor="#fdfdfe" class="vp-content" style="background-color: #fdfdfe; border: 1px solid #dbe4ea; border-radius: 12px; padding: 40px 44px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td bgcolor="#4E8EA2" style="width: 40px; height: 4px; font-size: 0; line-height: 0; border-radius: 2px; background-color: #4E8EA2;">&nbsp;</td>
                            </tr>
                        </table>
                        <div class="vp-body" style="padding-top: 14px; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #253745;">
                            {!! $body !!}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding: 28px 24px 8px 24px;">
                        <p style="margin: 0 0 8px 0; font-family: Inter, Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #5b6b78;">{{ lang('igniter.voxpilot::branding.mail.company_line') }}</p>
                        <p style="margin: 0; font-family: Inter, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #5b6b78;">{!! str_replace(e(config('voxpilot.support_email')), '<a href="mailto:'.e(config('voxpilot.support_email')).'" style="color: #4E8EA2; text-decoration: underline;">'.e(config('voxpilot.support_email')).'</a>', e(lang('igniter.voxpilot::branding.mail.support_line', ['email' => config('voxpilot.support_email')]))) !!}</p>
                        <p style="margin: 8px 0 0 0; font-family: Inter, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #8796a3;">&copy; {{ date('Y') }} VoxPilot</p>
                    </td>
                </tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td>
    </tr>
</table>
</body>
</html>
