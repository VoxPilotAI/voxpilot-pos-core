<div class="vp-widget">
    <h3 class="vp-widget-title">@lang('igniter.voxpilot::dashboard.best_sellers')</h3>
    @forelse($items as $item)
        <div class="vp-bar-row">
            <div class="vp-bar-label"><span>{{ $item['name'] }}</span><span>{{ trans_choice('igniter.voxpilot::dashboard.sold', $item['quantity']) }}</span></div>
            <div class="vp-bar-track"><span style="width: {{ $item['share'] }}%"></span></div>
        </div>
    @empty
        <p class="vp-empty">@lang('igniter.voxpilot::dashboard.no_orders_range')</p>
    @endforelse
</div>
