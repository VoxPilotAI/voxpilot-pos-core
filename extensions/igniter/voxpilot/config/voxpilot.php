<?php

return [
    'provisioning_secret' => env('VOXPILOT_PROVISIONING_SECRET'),
    'hmac_shared_secret' => env('VOXPILOT_HMAC_SECRET'),
    // Provisioning emails a newly created owner the TastyIgniter staff invite (set-password link).
    'send_owner_invite' => env('VOXPILOT_SEND_OWNER_INVITE', true),
    // SPEC-011: browser redirect target for Connect with VoxPilot (code + state only).
    'install_redirect_uri' => env(
        'VOXPILOT_APP_INSTALL_URL',
        'https://app.voxpilot.test/integrations/pos/install'
    ),
    // This POS has no diners: the storefront home becomes the VoxPilot POS landing page and the
    // customer login/register pages send people to the admin sign-in.
    'storefront_landing' => env('VOXPILOT_STOREFRONT_LANDING', true),
    // Languages with VoxPilot copy (landing page and owner emails); anything else falls back to English.
    'locales' => ['en', 'es', 'de', 'fr', 'it', 'pt', 'nl'],
    'brand_name' => env('VOXPILOT_BRAND_NAME', 'VoxPilot POS'),
    'marketing_url' => env('VOXPILOT_MARKETING_URL', 'https://voxpilothq.io'),
    'support_email' => env('VOXPILOT_SUPPORT_EMAIL', 'support@voxpilothq.io'),
];
