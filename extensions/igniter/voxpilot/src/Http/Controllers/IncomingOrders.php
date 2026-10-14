<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Http\Controllers;

use Igniter\Admin\Classes\AdminController;
use Igniter\Admin\Facades\Template;
use Igniter\Local\Models\Location;
use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Services\TenantContext;

class IncomingOrders extends AdminController
{
    use OrderQuickActions;

    protected null|string|array $requiredPermissions = ['Igniter.VoxPilot.Manage'];

    public function index()
    {
        Template::setTitle($this->pageTitle = lang('igniter.voxpilot::board.nav_live'));

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

        $recentOrders = VoxPilotOrderMetadata::with(['order', 'order.menus.menu_options', 'order.location', 'order.status'])
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

    /** After a status change: the ticket's status pill and its actions (Accept disappears). */
    protected function afterQuickStatusChange(Order $order): array
    {
        $order->loadMissing('status');

        return [
            '#vp-ticket-status-'.$order->order_id => $this->renderStatusPill($order),
            '#vp-ticket-actions-'.$order->order_id => $this->makePartial('ticketactions', ['order' => $order]),
        ];
    }

    /** Pill and actions for a ticket added live (the page builds the rest of the card). */
    public function onTicketExtras(): array
    {
        $order = $this->findQuickOrder();

        return [
            '#vp-ticket-status-'.$order->order_id => $this->renderStatusPill($order),
            '#vp-ticket-actions-'.$order->order_id => $this->makePartial('ticketactions', ['order' => $order]),
        ];
    }
}
