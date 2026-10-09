<section class="vp-auth-brand" aria-label="VoxPilot POS">
    <div class="vp-auth-logo">
        <img src="{{ asset('voxpilot/logo-on-dark.svg') }}" alt="VoxPilot" width="180" height="23">
        <span>POS</span>
    </div>
    <div class="vp-auth-pitch">
        <h2>@lang('igniter.voxpilot::auth.headline') <span>@lang('igniter.voxpilot::auth.headline_accent')</span></h2>
        <p>@lang('igniter.voxpilot::auth.subheadline')</p>
        <div class="vp-auth-ticket" aria-hidden="true">
            <div><strong>@lang('igniter.voxpilot::auth.ticket_title')</strong><em>@lang('igniter.voxpilot::auth.ticket_badge')</em></div>
            <div><span>2× Pizza Margherita, 1× Coca-Cola</span><b>€18.20</b></div>
        </div>
    </div>
    <p class="vp-auth-foot">&copy; {{ date('Y') }} VoxPilot · <a href="{{ config('voxpilot.marketing_url') }}" target="_blank" rel="noopener">voxpilothq.io</a></p>
</section>
