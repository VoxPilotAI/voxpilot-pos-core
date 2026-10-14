<div class="vp-widget">
    <div class="vp-widget-head">
        <div>
            <h3 class="vp-widget-title">@lang('igniter.voxpilot::dashboard.day_close')</h3>
            <p class="vp-widget-sub">{{ trans_choice('igniter.voxpilot::dashboard.day_close_sub', $summary['orders'], ['phone' => $summary['phoneOrders']]) }}</p>
        </div>
        <div class="vp-kpi-value">{{ currency_format($summary['revenue']) }}</div>
    </div>

    @if($summary['orders'] || $summary['canceled']['orders'])
        <div class="vp-day-grid">
            <dl class="vp-stat-list">
                <div class="vp-om-section-title">@lang('igniter.voxpilot::dashboard.by_payment')</div>
                @forelse($summary['payments'] as $payment)
                    <div>
                        <dt>@if($payment['unpaid'])<span class="vp-text-danger">@lang('igniter.voxpilot::dashboard.unpaid')</span>@else{{ $payment['label'] }}@endif · {{ $payment['orders'] }}</dt>
                        <dd>{{ currency_format($payment['total']) }}</dd>
                    </div>
                @empty
                    <div><dt>—</dt><dd>—</dd></div>
                @endforelse
            </dl>
            <dl class="vp-stat-list">
                <div class="vp-om-section-title">@lang('igniter.voxpilot::dashboard.by_type')</div>
                <div><dt><i class="fa fa-motorcycle"></i> @lang('igniter.voxpilot::live.delivery') · {{ $summary['types']['delivery']['orders'] }}</dt><dd>{{ currency_format($summary['types']['delivery']['total']) }}</dd></div>
                <div><dt><i class="fa fa-bag-shopping"></i> @lang('igniter.voxpilot::live.pickup') · {{ $summary['types']['collection']['orders'] }}</dt><dd>{{ currency_format($summary['types']['collection']['total']) }}</dd></div>
                <div><dt class="vp-text-danger"><i class="fa fa-ban"></i> @lang('igniter.voxpilot::dashboard.canceled') · {{ $summary['canceled']['orders'] }}</dt><dd>{{ currency_format($summary['canceled']['total']) }}</dd></div>
            </dl>
        </div>
    @else
        <p class="vp-empty">@lang('igniter.voxpilot::dashboard.no_orders_today')</p>
    @endif
</div>
