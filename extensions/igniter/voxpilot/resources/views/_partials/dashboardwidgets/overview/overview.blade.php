@php($cards = [
    ['label' => lang('igniter.voxpilot::dashboard.revenue'), 'value' => currency_format($kpis['revenue']), 'delta' => $kpis['revenueDelta'], 'spark' => $kpis['revenueSpark'], 'tone' => 'primary'],
    ['label' => lang('igniter.voxpilot::dashboard.orders'), 'value' => number_format($kpis['orders']), 'delta' => $kpis['ordersDelta'], 'spark' => $kpis['ordersSpark'], 'tone' => 'accent'],
    ['label' => lang('igniter.voxpilot::dashboard.average_ticket'), 'value' => currency_format($kpis['average']), 'delta' => $kpis['averageDelta'], 'spark' => $kpis['averageSpark'], 'tone' => 'primary'],
    ['label' => lang('igniter.voxpilot::dashboard.ai_phone_share'), 'value' => $kpis['phoneShare'].'%', 'note' => lang('igniter.voxpilot::dashboard.phone_of_total', ['phone' => $kpis['phoneOrders'], 'total' => $kpis['orders']]), 'spark' => $kpis['phoneSpark'], 'tone' => 'accent'],
])
<div class="vp-kpis">
    @foreach($cards as $card)
        <article class="vp-kpi">
            <div class="vp-kpi-head">
                <span class="vp-kpi-label">{{ $card['label'] }}</span>
                @if(isset($card['note']))
                    <span class="vp-pill vp-pill-primary">{{ $card['note'] }}</span>
                @elseif(!is_null($card['delta']))
                    <span class="vp-pill {{ $card['delta'] >= 0 ? 'vp-pill-success' : 'vp-pill-danger' }}">{{ $card['delta'] >= 0 ? '+' : '' }}{{ $card['delta'] }}%</span>
                @endif
            </div>
            <div class="vp-kpi-value">{{ $card['value'] }}</div>
            <div class="vp-spark vp-spark-{{ $card['tone'] }}" aria-hidden="true">
                @foreach($card['spark'] as $height)<span style="height: {{ $height }}%"></span>@endforeach
            </div>
        </article>
    @endforeach
</div>
