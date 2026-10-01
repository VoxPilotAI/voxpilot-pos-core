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
];
