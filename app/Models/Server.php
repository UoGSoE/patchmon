<?php

namespace App\Models;

use App\Enums\GraceUnit;
use App\Enums\OsType;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'name',
        'description',
        'location',
        'os_type',
        'interval_months',
        'grace_value',
        'grace_units',
        'patch_token',
        'notification_email',
        'sender_email',
        'last_patched_at',
        'alerting_since',
        'last_alerted_at',
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
            'last_patched_at' => 'datetime',
            'alerting_since' => 'datetime',
            'last_alerted_at' => 'datetime',
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
    }

    public function silenceUntil(Carbon $until, ?string $reason = null): void
    {
        $this->update([
            'silenced_until' => $until,
            'silence_reason' => $reason,
        ]);
    }

    public function unsilence(): void
    {
        $this->update([
            'silenced_until' => null,
            'silence_reason' => null,
        ]);
    }

    public function isCurrentlySilenced(): bool
    {
        return (bool) $this->silenced_until?->isFuture();
    }
}
