<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\TenantMembership;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;

class IncomingOrders extends AdminController
{
    protected null|string|array $requiredPermissions = ['Igniter.VoxPilot.Manage'];

    public function index()
    {
        $this->pageTitle = 'Incoming Orders';

        $user = $this->getUser();
        $membership = TenantMembership::where('user_id', $user->user_id)->first();

        if (!$membership) {
            $this->vars['tenant'] = null;
            $this->vars['locations'] = collect();
            $this->vars['recentOrders'] = collect();
            return;
        }

        $tenantId = $membership->tenant_id;
        $locations = Location::where('tenant_id', $tenantId)->get();

        $recentOrders = VoxPilotOrderMetadata::with('order')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $this->vars['tenant'] = $membership->tenant;
        $this->vars['tenantId'] = $tenantId;
        $this->vars['locations'] = $locations;
        $this->vars['recentOrders'] = $recentOrders;
    }
}
