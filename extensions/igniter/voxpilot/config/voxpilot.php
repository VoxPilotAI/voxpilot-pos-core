<?php

return [
    'provisioning_secret' => env('VOXPILOT_PROVISIONING_SECRET'),
    'hmac_shared_secret' => env('VOXPILOT_HMAC_SECRET'),
    // SPEC-011: browser redirect target for Connect with VoxPilot (code + state only).
    'install_redirect_uri' => env(
        'VOXPILOT_APP_INSTALL_URL',
        'https://app.voxpilot.test/integrations/pos/install'
    ),
];
