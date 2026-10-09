{{-- VoxPilot POS sign-in (overrides igniter.user::auth.login; same form and handler). --}}
<div class="vp-auth">
    @include('igniter.voxpilot::auth.brand')
    <section class="vp-auth-form">
        {!! form_open([
            'id' => 'edit-form',
            'role' => 'form',
            'method' => 'POST',
            'data-request' => 'onLogin',
        ]) !!}
            <h1>@lang('igniter.voxpilot::auth.login_title')</h1>
            <p class="vp-auth-sub">@lang('igniter.voxpilot::auth.login_sub')</p>
            <div class="form-group">
                <label for="input-email" class="form-label">@lang('igniter.user::default.login.label_email')</label>
                <input name="email" type="email" id="input-email" class="form-control" autocomplete="username" placeholder="owner@yourrestaurant.com"/>
                {!! form_error('email', '<span class="text-danger">', '</span>') !!}
            </div>
            <div class="form-group">
                <div class="d-flex justify-content-between align-items-baseline">
                    <label for="input-password" class="form-label">@lang('igniter.user::default.login.label_password')</label>
                    <a class="vp-auth-link" href="{{ admin_url('login/reset') }}">@lang('igniter.user::default.login.text_forgot_password')</a>
                </div>
                <input name="password" type="password" id="input-password" class="form-control" autocomplete="current-password"/>
                {!! form_error('password', '<span class="text-danger">', '</span>') !!}
            </div>
            <button type="submit" class="btn btn-primary w-100" data-attach-loading="">@lang('igniter.user::default.login.button_login')</button>
            <p class="vp-auth-note">@lang('igniter.voxpilot::auth.new_here')</p>
        {!! form_close() !!}
    </section>
</div>
