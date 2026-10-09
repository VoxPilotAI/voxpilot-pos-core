<div class="vp-widget">
    <div class="vp-widget-head">
        <h3 class="vp-widget-title">@lang('igniter.voxpilot::dashboard.your_assistant')</h3>
        @if($stats)
            <span class="vp-pill vp-pill-success"><i class="vp-dot vp-bg-success"></i>@lang('igniter.voxpilot::dashboard.connected')</span>
        @else
            <span class="vp-pill vp-pill-muted">@lang('igniter.voxpilot::dashboard.not_connected')</span>
        @endif
    </div>
    @if($stats)
        <div class="vp-assistant">
            <div class="vp-ring" style="--vp-ring: {{ $conversion ?? 0 }}%">
                <div><strong>{{ is_null($conversion) ? '—' : $conversion.'%' }}</strong><span>@lang('igniter.voxpilot::dashboard.calls_to_orders')</span></div>
            </div>
            <dl class="vp-stat-list">
                <div><dt>@lang('igniter.voxpilot::dashboard.calls_answered')</dt><dd>{{ $stats['calls']['answered'] }}</dd></div>
                <div><dt>@lang('igniter.voxpilot::dashboard.orders_confirmed')</dt><dd>{{ $stats['orders']['confirmed'] }}</dd></div>
                <div><dt>@lang('igniter.voxpilot::dashboard.average_call')</dt><dd>{{ $avgCall }}</dd></div>
                <div><dt>@lang('igniter.voxpilot::dashboard.missed_calls')</dt><dd class="{{ $stats['calls']['missed'] ? 'vp-text-danger' : 'vp-text-success' }}">{{ $stats['calls']['missed'] }}</dd></div>
            </dl>
        </div>
        @if(!empty($stats['assistant']['name']))
            <p class="vp-widget-sub">@lang('igniter.voxpilot::dashboard.assistant_name', ['name' => $stats['assistant']['name']])</p>
        @endif
        @if(!empty($stats['busiestHour']))
            <div class="vp-insight">@lang('igniter.voxpilot::dashboard.busiest_hour', ['from' => sprintf('%02d:00', $stats['busiestHour']['hour']), 'to' => sprintf('%02d:00', ($stats['busiestHour']['hour'] + 1) % 24), 'orders' => $stats['busiestHour']['orders']])</div>
        @endif
    @else
        <p class="vp-widget-sub">@lang('igniter.voxpilot::dashboard.assistant_unavailable', ['orders' => $phoneOrders])</p>
        <a class="btn btn-outline-primary" href="{{ admin_url('igniter/voxpilot/integrations') }}">@lang('igniter.voxpilot::dashboard.open_voxpilot')</a>
    @endif
</div>
