@if($store)
    <div class="vp-store">
        <div class="vp-store-state">
            @if($store['paused'])
                <span class="vp-pill vp-pill-danger"><i class="vp-dot vp-bg-danger"></i>@lang('igniter.voxpilot::board.store_paused')</span>
            @elseif($store['within_hours'] === false)
                <span class="vp-pill vp-pill-muted"><i class="vp-dot"></i>@lang('igniter.voxpilot::board.store_closed_hours')</span>
            @else
                <span class="vp-pill vp-pill-success"><i class="vp-dot vp-bg-success"></i>@lang('igniter.voxpilot::board.store_open')</span>
            @endif
            <span class="vp-store-times">{{ lang('igniter.voxpilot::board.lead_times', ['delivery' => $store['delivery_lead_time'], 'pickup' => $store['collection_lead_time']]) }}</span>
        </div>

        <div class="vp-store-controls">
            <div class="vp-store-group">
                <span class="vp-store-label">@lang('igniter.voxpilot::board.busy')</span>
                <div class="vp-segmented" role="group" aria-label="@lang('igniter.voxpilot::board.busy')">
                    @foreach(\Igniter\VoxPilot\Services\StoreStatus::BUSY_STEPS as $minutes)
                        <button type="button" class="{{ $store['busy_minutes'] === $minutes ? 'active' : '' }}"
                                @if($store['busy_minutes'] === $minutes) aria-pressed="true" @endif
                                data-request="onSetStoreBusy" data-request-data="minutes: {{ $minutes }}">
                            {{ $minutes ? lang('igniter.voxpilot::board.busy_plus', ['min' => $minutes]) : lang('igniter.voxpilot::board.busy_normal') }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="vp-store-group">
                @foreach(['delivery' => ['fa-motorcycle', 'igniter.voxpilot::live.delivery'], 'collection' => ['fa-bag-shopping', 'igniter.voxpilot::live.pickup']] as $type => [$icon, $label])
                    @php($on = $store[$type.'_enabled'])
                    <button type="button" class="vp-switch {{ $on ? 'on' : '' }}" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}"
                            data-request="onSetStoreOrderType" data-request-data="type: '{{ $type }}', enabled: {{ $on ? 0 : 1 }}">
                        <i class="fa {{ $icon }}"></i> @lang($label)
                        <span class="vp-switch-track" aria-hidden="true"><span></span></span>
                    </button>
                @endforeach
            </div>

            <div class="vp-store-group">
                <button type="button" class="btn btn-light"
                        data-request="onOpenAvailability" data-request-success="vpOpenOrderModal()">
                    <i class="fa fa-utensils"></i> @lang('igniter.voxpilot::board.availability')
                    @if($soldOut)<span class="vp-count vp-count-danger">{{ $soldOut }}</span>@endif
                </button>
                @if($store['paused'])
                    <button type="button" class="btn btn-light vp-text-success" data-request="onSetStorePaused" data-request-data="paused: 0">
                        <i class="fa fa-play"></i> @lang('igniter.voxpilot::board.resume')
                    </button>
                @else
                    <button type="button" class="btn btn-light vp-om-cancel" data-request="onSetStorePaused" data-request-data="paused: 1"
                            data-request-confirm="@lang('igniter.voxpilot::board.pause_confirm')">
                        <i class="fa fa-pause"></i> @lang('igniter.voxpilot::board.pause')
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
