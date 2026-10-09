<div class="vp-board">
    @foreach($columns as $column)
        <section class="vp-column" data-vp-column style="--vp-status: {{ $column['color'] }}">
            <header class="vp-column-head">
                <h2><i class="vp-dot" style="background: {{ $column['color'] }}"></i>{{ $column['name'] }}</h2>
                <span class="vp-count" data-vp-count>{{ count($column['cards']) }}</span>
            </header>
            @forelse($column['cards'] as $card)
                <article class="vp-card {{ $card['phone'] && $card['minutes'] < 5 && !$column['done'] ? 'vp-card-new' : '' }}" data-vp-card data-source="{{ $card['phone'] ? 'phone' : 'other' }}">
                    <div class="vp-card-head">
                        <div>
                            <a class="vp-card-id" href="{{ admin_url('orders/edit/'.$card['id']) }}">#{{ $card['id'] }}</a>
                            <div class="vp-card-customer">{{ $card['customer'] }}</div>
                        </div>
                        <span class="vp-pill {{ !$column['done'] && $card['minutes'] >= 30 ? 'vp-pill-danger' : 'vp-pill-muted' }}" title="@lang('igniter.voxpilot::board.waiting')">
                            <i class="fa fa-clock"></i> {{ $card['minutes'] < 60 ? $card['minutes'].' min' : intdiv($card['minutes'], 60).' h' }}
                        </span>
                    </div>
                    <ul class="vp-card-lines">
                        @foreach($card['lines'] as $line)
                            <li><span>{{ $line['name'] }}</span><span>×{{ $line['quantity'] }}</span></li>
                        @endforeach
                    </ul>
                    @if($card['comment'])
                        <div class="vp-card-note">{{ $card['comment'] }}</div>
                    @endif
                    <div class="vp-card-foot">
                        <span class="vp-pill {{ $card['phone'] ? 'vp-pill-primary' : 'vp-pill-muted' }}">{{ $card['phone'] ? lang('igniter.voxpilot::dashboard.ai_phone') : lang('igniter.voxpilot::dashboard.other') }} · {{ $card['type'] }}</span>
                        <strong>{{ currency_format($card['total']) }}</strong>
                    </div>
                    @if($column['next'])
                        <button type="button" class="vp-card-action"
                                data-request="onMoveOrder"
                                data-request-data="order_id: {{ $card['id'] }}, status_id: {{ $column['next']['id'] }}">
                            @lang('igniter.voxpilot::board.move_to', ['status' => $column['next']['name']])
                        </button>
                    @endif
                </article>
            @empty
                <p class="vp-column-empty">@lang('igniter.voxpilot::board.empty')</p>
            @endforelse
        </section>
    @endforeach
</div>
