<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Models;

use Igniter\Flame\Database\Model;
use Igniter\Local\Models\Location;
use Igniter\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantApiToken extends Model
{
    protected $table = 'voxpilot_tenant_api_tokens';

    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'default_location_id',
        'name',
        'token_hash',
        'abilities',
        'revoked_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id', 'location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** Whether the token was issued for one of these abilities. */
    public function canAny(string ...$abilities): bool
    {
        return (bool) array_intersect($abilities, (array) $this->abilities);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public static function generateToken(
        int $tenantId,
        string $name,
        ?int $defaultLocationId = null,
        ?int $createdByUserId = null,
        array $abilities = ['orders:create'],
    ): array {
        $plainText = 'vp_pos_' . Str::random(48);
        $hash = hash('sha256', $plainText);

        $token = static::create([
            'tenant_id' => $tenantId,
            'default_location_id' => $defaultLocationId,
            'name' => $name,
            'token_hash' => $hash,
            'abilities' => $abilities,
            'created_by_user_id' => $createdByUserId,
        ]);

        return [
            'token' => $token,
            'plain_text' => $token->id . '|' . $plainText,
        ];
    }

    public static function findByBearerToken(string $bearerToken): ?static
    {
        $parts = explode('|', $bearerToken, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$tokenId, $plainText] = $parts;

        $token = static::find($tokenId);
        if (!$token) {
            return null;
        }

        if (!hash_equals($token->token_hash, hash('sha256', $plainText))) {
            return null;
        }

        return $token;
    }
}
