<?php

namespace App\Models;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use App\Events\ActivityOccurred;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property string $name
 */
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'netbox_id',
        'is_virtual',
        'inactive_since',
        'name',
        'description',
        'location',
        'os_type',
        'interval_months',
        'grace_value',
        'grace_units',
        'patch_token',
        'patch_token_provisioned_at',
        'notification_email',
        'sender_email',
        'last_patched_at',
        'alerting_since',
        'last_alerted_at',
        'silenced_from',
        'silenced_until',
        'silence_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Server $server): void {
            if (! $server->patch_token) {
                $server->patch_token = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'os_type' => OsType::class,
            'grace_units' => GraceUnit::class,
            'is_virtual' => 'boolean',
            'inactive_since' => 'datetime',
            'patch_token_provisioned_at' => 'datetime',
            'last_patched_at' => 'datetime',
            'alerting_since' => 'datetime',
            'last_alerted_at' => 'datetime',
            'silenced_from' => 'datetime',
            'silenced_until' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function patchEvents(): HasMany
    {
        return $this->hasMany(PatchEvent::class);
    }

    /**
     * The live, monitored estate: servers that belong to a team and are not
     * decommissioned. Exactly the set the alert evaluator acts on.
     */
    public function scopeMonitored(Builder $query): void
    {
        $query->whereNull('inactive_since')->whereNotNull('team_id');
    }

    /**
     * Servers that exist but have never reported a patch, across the whole live
     * estate — any team or none. Deliberately wider than the monitored estate so
     * that half-set-up triage servers are caught; decommissioned servers are
     * excluded, as a retired box that never reported is expected, not a problem.
     */
    public function scopeNeverCheckedIn(Builder $query): void
    {
        $query->whereNull('last_patched_at')->whereNull('inactive_since');
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : strtolower($value),
        );
    }

    public function resolveNotificationEmail(): string
    {
        return $this->notification_email ?? $this->team->notification_email;
    }

    public function resolveSenderEmail(): ?string
    {
        return $this->sender_email ?? $this->team->sender_email;
    }

    public function isOverdue(): bool
    {
        return now()->greaterThanOrEqualTo($this->deadline());
    }

    public function daysOverdue(): int
    {
        return (int) floor($this->deadline()->diffInDays(now()));
    }

    public function deadline(): Carbon
    {
        $reference = $this->last_patched_at ?? $this->created_at;
        $base = $reference->copy()->addMonthsNoOverflow($this->interval_months);

        return $this->grace_units->addTo($base, $this->grace_value);
    }

    public function intervalLabel(): string
    {
        return match ($this->interval_months) {
            1 => 'Monthly',
            3 => 'Quarterly',
            6 => 'Twice-yearly',
            12 => 'Yearly',
            default => "Every {$this->interval_months} months",
        };
    }

    public function recordPatch(
        ?User $patchedBy = null,
        ?string $notes = null,
        ?string $sourceIp = null,
        ?Carbon $at = null,
    ): PatchEvent {
        $at ??= now();

        $patchEvent = DB::transaction(function () use ($patchedBy, $notes, $sourceIp, $at): PatchEvent {
            /** @var PatchEvent $patchEvent */
            $patchEvent = $this->patchEvents()->create([
                'patched_by' => $patchedBy?->id,
                'patched_at' => $at,
                'source_ip' => $sourceIp,
                'notes' => $notes,
            ]);

            $this->last_patched_at = $at;
            $this->alerting_since = null;
            $this->last_alerted_at = null;
            $this->save();

            return $patchEvent;
        });

        ActivityOccurred::dispatch($patchedBy?->id, $this->id, 'Recorded a patch', $sourceIp);

        return $patchEvent;
    }

    public function regenerateToken(): void
    {
        // Issues a fresh record-patch token (the old URL stops working) and clears any
        // provisioning claim, so a rebuilt machine can provision a new token on its next run.
        $this->update([
            'patch_token' => (string) Str::uuid(),
            'patch_token_provisioned_at' => null,
        ]);
    }

    public function silenceBetween(Carbon $from, Carbon $until, ?string $reason = null): void
    {
        $this->update([
            'silenced_from' => $from,
            'silenced_until' => $until,
            'silence_reason' => $reason,
        ]);
    }

    public function unsilence(): void
    {
        $this->update([
            'silenced_from' => null,
            'silenced_until' => null,
            'silence_reason' => null,
        ]);
    }

    public function isCurrentlySilenced(): bool
    {
        if (! $this->silenced_from || ! $this->silenced_until) {
            return false;
        }

        return now()->betweenIncluded($this->silenced_from, $this->silenced_until);
    }

    public function isUnassigned(): bool
    {
        return $this->team_id === null;
    }

    public function isInactive(): bool
    {
        return $this->inactive_since !== null;
    }
}
