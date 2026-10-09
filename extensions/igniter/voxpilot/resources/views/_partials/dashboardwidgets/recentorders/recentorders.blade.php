<div class="vp-widget">
    <div class="vp-widget-head">
        <h3 class="vp-widget-title">@lang('igniter.voxpilot::dashboard.recent_orders')</h3>
        <a class="vp-link" href="{{ admin_url('igniter/voxpilot/board') }}">@lang('igniter.voxpilot::dashboard.open_board')</a>
    </div>
    @if(count($orders))
        <div class="table-responsive">
            <table class="vp-table">
                <thead><tr><th>@lang('igniter.voxpilot::dashboard.order')</th><th>@lang('igniter.voxpilot::dashboard.customer')</th><th>@lang('igniter.voxpilot::dashboard.source')</th><th>@lang('igniter.voxpilot::dashboard.status')</th><th class="text-end">@lang('igniter.voxpilot::dashboard.total')</th></tr></thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><a class="vp-strong" href="{{ admin_url('orders/edit/'.$order['id']) }}">#{{ $order['id'] }}</a><span class="vp-sub">{{ $order['time'] }}</span></td>
                        <td>{{ $order['customer'] }}<span class="vp-sub vp-truncate">{{ $order['items'] }}</span></td>
                        <td><span class="vp-pill {{ $order['phone'] ? 'vp-pill-primary' : 'vp-pill-muted' }}">{{ $order['phone'] ? lang('igniter.voxpilot::dashboard.ai_phone') : lang('igniter.voxpilot::dashboard.other') }}</span></td>
                        <td><span class="vp-pill vp-pill-status" style="--vp-status: {{ $order['color'] }}">{{ $order['status'] }}</span></td>
                        <td class="text-end vp-strong">{{ currency_format($order['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="vp-empty">@lang('igniter.voxpilot::dashboard.no_orders_yet')</p>
    @endif
</div>
