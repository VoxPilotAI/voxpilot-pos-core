<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Console;

use Igniter\System\Models\Settings;
use Illuminate\Console\Command;

/**
 * Names the POS after the brand. The site name is the browser tab title suffix and the sender name
 * in every email; `igniter:install` would ask for it, but the production image never runs it, so
 * the entrypoint calls this on every start. A name set in the admin settings is kept.
 */
class ApplyBranding extends Command
{
    protected $signature = 'voxpilot:brand {--force : Overwrite a site name set in the admin}';

    protected $description = 'Set the VoxPilot POS site name when none is configured';

    private const DEFAULT_NAMES = ['', 'TastyIgniter'];

    public function handle(): int
    {
        $brandName = (string) config('voxpilot.brand_name', 'VoxPilot POS');
        $current = trim((string) Settings::get('site_name', ''));

        if (!$this->option('force') && !in_array($current, self::DEFAULT_NAMES, true)) {
            $this->line("Site name kept: {$current}");

            return self::SUCCESS;
        }

        Settings::set('site_name', $brandName);
        $this->info("Site name set to {$brandName}");

        return self::SUCCESS;
    }
}
