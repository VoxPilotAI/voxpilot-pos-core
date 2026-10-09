{{-- VoxPilot POS password reset and invite acceptance (overrides igniter.user::auth.reset; same handlers). --}}
<div class="vp-auth">
    @include('igniter.voxpilot::auth.brand')
    <section class="vp-auth-form">
        {!! form_open(current_url(), [
            'id' => 'edit-form',
            'role' => 'form',
            'method' => 'POST',
            'data-request' => empty($resetCode) ? 'onRequestResetPassword' : 'onResetPassword',
        ]) !!}
            @empty($resetCode)
                <h1>@lang('igniter.voxpilot::auth.reset_title')</h1>
                <p class="vp-auth-sub">@lang('igniter.voxpilot::auth.reset_sub')</p>
                <div class="form-group">
                    <label for="input-user" class="form-label">@lang('igniter::admin.label_email')</label>
                    <input name="email" type="email" id="input-user" class="form-control" autocomplete="username"/>
                    {!! form_error('email', '<span class="text-danger">', '</span>') !!}
                </div>
            @else
                <h1>@lang('igniter.voxpilot::auth.set_title')</h1>
                <p class="vp-auth-sub">@lang('igniter.voxpilot::auth.set_sub')</p>
                <input type="hidden" name="code" value="{{ $resetCode }}">
                <div class="form-group">
                    <label for="password" class="form-label">@lang('igniter.user::default.login.label_password')</label>
                    <input type="password" id="password" class="form-control" name="password" autocomplete="new-password"/>
                    {!! form_error('password', '<span class="text-danger">', '</span>') !!}
                </div>
                <div class="form-group">
                    <label for="password-confirm" class="form-label">@lang('igniter.user::default.login.label_password_confirm')</label>
                    <input type="password" id="password-confirm" class="form-control" name="password_confirm" autocomplete="new-password"/>
                    {!! form_error('password_confirm', '<span class="text-danger">', '</span>') !!}
                </div>
            @endempty
            <button type="submit" class="btn btn-primary w-100" data-attach-loading="">
                {{ empty($resetCode) ? lang('igniter.user::default.login.button_reset_password') : lang('igniter.voxpilot::auth.set_button') }}
            </button>
            <p class="vp-auth-note"><a class="vp-auth-link" href="{{ admin_url('login') }}">@lang('igniter.user::default.login.text_back_to_login')</a></p>
        {!! form_close() !!}
    </section>
</div>
