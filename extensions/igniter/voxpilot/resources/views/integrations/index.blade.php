<div class="row-fluid">
    <div class="d-flex align-items-center mb-4">
        <h4 class="mb-0">{{ lang('igniter.voxpilot::integrations.title') }}</h4>
    </div>

    @php
        $isConnected = ($installation->status ?? null) === 'CONNECTED';
    @endphp

    @if($tenantId)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ lang('igniter.voxpilot::integrations.connection') }}</h5>
            </div>
            <div class="card-body">
                @if($isConnected)
                    <p class="mb-3">
                        <span class="badge bg-success">{{ lang('igniter.voxpilot::integrations.connected') }}</span>
                        @if($installation?->assistant_id)
                            <span class="text-muted ms-2">{{ lang('igniter.voxpilot::integrations.assistant') }} {{ $installation->assistant_id }}</span>
                        @endif
                    </p>
                    <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onDisconnect') }}"
                          onsubmit="return confirm({{ json_encode(lang('igniter.voxpilot::integrations.disconnect_confirm')) }});">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa fa-unlink"></i> {{ lang('igniter.voxpilot::integrations.disconnect') }}
                        </button>
                    </form>
                @else
                    <p class="mb-3 text-muted">
                        {{ lang('igniter.voxpilot::integrations.not_connected_help') }}
                        {{ lang('igniter.voxpilot::integrations.status') }} <code>{{ $installation->status ?? 'NOT_CONNECTED' }}</code>
                    </p>
                    <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onConnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-plug"></i> {{ lang('igniter.voxpilot::integrations.connect') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- Show new token once --}}
    @if($newToken)
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <h5 class="alert-heading">{{ lang('igniter.voxpilot::integrations.token_created') }}</h5>
            <p class="mb-1">{!! lang('igniter.voxpilot::integrations.token_copy_help') !!}</p>
            <div class="input-group mb-2" style="max-width: 600px;">
                <input type="text" class="form-control font-monospace" value="{{ $newToken }}" id="newTokenValue" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newTokenValue').value); this.textContent={{ json_encode(lang('igniter.voxpilot::integrations.copied')) }};">
                    {{ lang('igniter.voxpilot::integrations.copy') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Advanced: manual tokens (local/dev) --}}
    @if($tenantId)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ lang('igniter.voxpilot::integrations.advanced_tokens') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">{!! lang('igniter.voxpilot::integrations.advanced_help') !!}</p>
                <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onCreate') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">{{ lang('igniter.voxpilot::integrations.token_name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="{{ lang('igniter.voxpilot::integrations.token_name_placeholder') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ lang('igniter.voxpilot::integrations.default_location') }}</label>
                            <select name="default_location_id" class="form-select">
                                <option value="">{{ lang('igniter.voxpilot::integrations.auto_resolve') }}</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->location_id }}">{{ $location->location_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fa fa-key"></i> {{ lang('igniter.voxpilot::integrations.generate_token') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ lang('igniter.voxpilot::integrations.api_tokens') }}</h5>
            </div>
            <div class="card-body p-0">
                @if($tokens->isEmpty())
                    <div class="p-4 text-muted text-center">
                        {{ lang('igniter.voxpilot::integrations.no_tokens') }}
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ lang('igniter.voxpilot::integrations.col_name') }}</th>
                                    <th>{{ lang('igniter.voxpilot::integrations.col_default_location') }}</th>
                                    <th>{{ lang('igniter.voxpilot::integrations.col_created') }}</th>
                                    <th>{{ lang('igniter.voxpilot::integrations.col_last_used') }}</th>
                                    <th>{{ lang('igniter.voxpilot::integrations.col_status') }}</th>
                                    <th class="text-end">{{ lang('igniter.voxpilot::integrations.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tokens as $token)
                                    <tr class="{{ $token->isRevoked() ? 'text-muted' : '' }}">
                                        <td>{{ $token->name }}</td>
                                        <td>{{ $token->defaultLocation?->location_name ?? '—' }}</td>
                                        <td>{{ $token->created_at?->diffForHumans() }}</td>
                                        <td>{{ $token->last_used_at?->diffForHumans() ?? lang('igniter.voxpilot::integrations.never') }}</td>
                                        <td>
                                            @if($token->isRevoked())
                                                <span class="badge bg-danger">{{ lang('igniter.voxpilot::integrations.revoked') }}</span>
                                            @else
                                                <span class="badge bg-success">{{ lang('igniter.voxpilot::integrations.active') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(!$token->isRevoked())
                                                <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onRevoke') }}" class="d-inline" onsubmit="return confirm({{ json_encode(lang('igniter.voxpilot::integrations.revoke_confirm')) }});">
                                                    @csrf
                                                    <input type="hidden" name="token_id" value="{{ $token->id }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa fa-ban"></i> {{ lang('igniter.voxpilot::integrations.revoke') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            {!! lang('igniter.voxpilot::integrations.no_tenant') !!}
        </div>
    @endif
</div>
