<div class="row-fluid">
    <div class="d-flex align-items-center mb-4">
        <h4 class="mb-0">VoxPilot Integration</h4>
    </div>

    @php
        $isConnected = ($installation->status ?? null) === 'CONNECTED';
    @endphp

    @if($tenantId)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Connection</h5>
            </div>
            <div class="card-body">
                @if($isConnected)
                    <p class="mb-3">
                        <span class="badge bg-success">VoxPilot — Connected</span>
                        @if($installation?->assistant_id)
                            <span class="text-muted ms-2">Assistant: {{ $installation->assistant_id }}</span>
                        @endif
                    </p>
                    <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onDisconnect') }}"
                          onsubmit="return confirm('Disconnect VoxPilot? Orders will stop until you connect again. The POS tenant is kept.');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa fa-unlink"></i> Disconnect VoxPilot
                        </button>
                    </form>
                @else
                    <p class="mb-3 text-muted">
                        Connect this restaurant to VoxPilot to choose a voice agent and enable order dispatch.
                        Status: <code>{{ $installation->status ?? 'NOT_CONNECTED' }}</code>
                    </p>
                    <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onConnect') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-plug"></i> Connect with VoxPilot
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
            <h5 class="alert-heading">API Token Created</h5>
            <p class="mb-1">Copy this token now. It will <strong>not</strong> be shown again:</p>
            <div class="input-group mb-2" style="max-width: 600px;">
                <input type="text" class="form-control font-monospace" value="{{ $newToken }}" id="newTokenValue" readonly>
                <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newTokenValue').value); this.textContent='Copied!';">
                    Copy
                </button>
            </div>
        </div>
    @endif

    {{-- Advanced: manual tokens (local/dev) --}}
    @if($tenantId)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Advanced — API Tokens</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Prefer <strong>Connect with VoxPilot</strong> above. Manual tokens are for local testing only.</p>
                <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onCreate') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Token Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Local debug" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Location</label>
                            <select name="default_location_id" class="form-select">
                                <option value="">— Auto-resolve —</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->location_id }}">{{ $location->location_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fa fa-key"></i> Generate Token
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">API Tokens</h5>
            </div>
            <div class="card-body p-0">
                @if($tokens->isEmpty())
                    <div class="p-4 text-muted text-center">
                        No API tokens yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Default Location</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tokens as $token)
                                    <tr class="{{ $token->isRevoked() ? 'text-muted' : '' }}">
                                        <td>{{ $token->name }}</td>
                                        <td>{{ $token->defaultLocation?->location_name ?? '—' }}</td>
                                        <td>{{ $token->created_at?->diffForHumans() }}</td>
                                        <td>{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td>
                                            @if($token->isRevoked())
                                                <span class="badge bg-danger">Revoked</span>
                                            @else
                                                <span class="badge bg-success">Active</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if(!$token->isRevoked())
                                                <form method="POST" action="{{ admin_url('igniter/voxpilot/integrations/onRevoke') }}" class="d-inline" onsubmit="return confirm('Revoke this token? This cannot be undone.');">
                                                    @csrf
                                                    <input type="hidden" name="token_id" value="{{ $token->id }}">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa fa-ban"></i> Revoke
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
            <strong>No tenant configured.</strong> Run <code>php artisan voxpilot:bootstrap-tenant</code> to set up.
        </div>
    @endif
</div>
