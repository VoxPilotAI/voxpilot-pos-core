<div class="vp-widget">
    <div class="vp-widget-head">
        <div>
            <h3 class="vp-widget-title">@lang('igniter.voxpilot::dashboard.orders_by_hour')</h3>
            <p class="vp-widget-sub">@lang('igniter.voxpilot::dashboard.orders_by_hour_help')</p>
        </div>
        <div class="vp-legend">
            <span><i class="vp-dot vp-bg-primary"></i>@lang('igniter.voxpilot::dashboard.ai_phone')</span>
            <span><i class="vp-dot vp-bg-accent"></i>@lang('igniter.voxpilot::dashboard.other_channels')</span>
        </div>
    </div>
    @if(count($hours))
        <div class="vp-hours" role="img" aria-label="@lang('igniter.voxpilot::dashboard.orders_by_hour')">
            @foreach($hours as $hour)
                <div class="vp-hour" title="{{ sprintf('%02d:00', $hour['hour']) }} · {{ $hour['phone'] + $hour['other'] }}">
                    <div class="vp-hour-bars">
                        <span class="vp-bg-accent" style="height: {{ $hour['otherHeight'] }}%"></span>
                        <span class="vp-bg-primary" style="height: {{ $hour['phoneHeight'] }}%"></span>
                    </div>
                    <span class="vp-hour-label">{{ sprintf('%02d', $hour['hour']) }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p class="vp-empty">@lang('igniter.voxpilot::dashboard.no_orders_range')</p>
    @endif
</div>
