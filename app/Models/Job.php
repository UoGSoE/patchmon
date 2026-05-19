<?php

namespace App\Models;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    protected $table = 'monitored_jobs';

    protected $fillable = [
        'team_id',
        'user_id',
        'created_by_user_id',
        'name',
        'description',
        'cron_expression',
        'schedule_interval',
        'schedule_frequency',
        'grace_value',
        'grace_units',
        'check_in_token',
        'requires_bearer_token',
        'notification_email',
        'sender_email',
        'last_checked_in_at',
        'alerting_since',
        'silenced_until',
        'silence_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Job $job): void {
            if (! $job->check_in_token) {
                $job->check_in_token = (string) Str::uuid();
            }

            if (! $job->isDirty('requires_bearer_token')) {
                $owner = $job->team_id ? $job->team : $job->user;
                $job->requires_bearer_token = (bool) ($owner?->check_ins_require_token ?? false);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'schedule_interval' => ScheduleInterval::class,
            'grace_units' => GraceUnit::class,
            'requires_bearer_token' => 'boolean',
            'last_checked_in_at' => 'datetime',
            'alerting_since' => 'datetime',
            'silenced_until' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function resolveNotificationEmail(): string
    {
        if ($this->notification_email) {
            return $this->notification_email;
        }

        if ($this->team_id) {
            return $this->team->notification_email;
        }

        return $this->user->notification_email ?? $this->user->email;
    }

    public function resolveSenderEmail(): ?string
    {
        if ($this->sender_email) {
            return $this->sender_email;
        }

        if ($this->team_id) {
            return $this->team->sender_email;
        }

        return $this->user->sender_email;
    }

    public function isCurrentlySilenced(): bool
    {
        if ($this->silenced_until?->isFuture()) {
            return true;
        }

        $owner = $this->team_id ? $this->team : $this->user;

        return (bool) $owner?->silenced_until?->isFuture();
    }
}
