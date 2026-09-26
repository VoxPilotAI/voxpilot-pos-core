<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Services\TenantContext;

class IncomingOrders extends AdminController
{
    protected null|string|array $requiredPermissions = ['Igniter.VoxPilot.Manage'];

    public function index()
    {
        $this->pageTitle = 'Incoming Orders';

        $user = $this->getUser();
        $context = app(TenantContext::class);
        $tenant = $context->resolveForAdmin($user);

        if (!$tenant) {
            $this->vars['tenant'] = null;
            $this->vars['locations'] = collect();
            $this->vars['recentOrders'] = collect();
            $this->vars['reverbConfig'] = null;
            return;
        }

        $tenantId = $tenant->id;
        $locations = Location::where('tenant_id', $tenantId)->get();

        $recentOrders = VoxPilotOrderMetadata::with(['order', 'order.menus', 'order.location'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->vars['tenant'] = $tenant;
        $this->vars['tenantId'] = $tenantId;
        $this->vars['locations'] = $locations;
        $this->vars['recentOrders'] = $recentOrders;
        $this->vars['reverbConfig'] = [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => env('REVERB_HOST', request()->getHost()),
            'port' => (int) env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
            'authEndpoint' => url('broadcasting/auth'),
        ];
    }
}
