{{-- Admin header language switcher; vp-admin.js moves it into the header menu. --}}
<template id="vp-language-menu">
    <li class="nav-item dropdown vp-language">
        <button type="button" class="nav-link border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="@lang('igniter.voxpilot::language.title')" title="@lang('igniter.voxpilot::language.title')">
            <i class="fa fa-globe" aria-hidden="true"></i><span class="vp-language-code">{{ strtoupper($current) }}</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end vp-device-menu">
            <form method="POST" action="{{ admin_url('igniter/voxpilot/language/onSetLanguage') }}">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="vp-om-section-title px-2 pt-1">@lang('igniter.voxpilot::language.title')</div>
                @foreach($languages as $code => $name)
                    <button type="submit" name="language" value="{{ $code }}" class="vp-device-option {{ $code === $current ? 'on' : '' }}" @if($code === $current) aria-current="true" @endif>
                        <span class="vp-language-code">{{ strtoupper($code) }}</span> {{ $name }}
                        @if($code === $current)<i class="fa fa-check ms-auto" aria-hidden="true"></i>@endif
                    </button>
                @endforeach
                @if($canTeam)
                    <label class="vp-device-option vp-language-team">
                        <input type="checkbox" class="form-check-input" name="team" value="1" @checked($tenantLocale === null || $tenantLocale === $current)>
                        <span>@lang('igniter.voxpilot::language.whole_team')<small>@lang('igniter.voxpilot::language.whole_team_help')</small></span>
                    </label>
                @endif
            </form>
        </div>
    </li>
</template>
