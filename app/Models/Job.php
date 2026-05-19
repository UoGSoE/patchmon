<?php

namespace App\Models;

use App\Enums\GraceUnit;
use App\Enums\ScheduleInterval;
use Cron\CronExpression;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'notification_email',
        'sender_email',
        'last_checked_in_at',
        'alerting_since',
        'last_alerted_at',
        'silenced_until',
        'silence_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Job $job): void {
            if (! $job->check_in_token) {
                $job->check_in_token = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'schedule_interval' => ScheduleInterval::class,
            'grace_units' => GraceUnit::class,
            'last_checked_in_at' => 'datetime',
            'alerting_since' => 'datetime',
            'last_alerted_at' => 'datetime',
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

    public function isOverdue(): bool
    {
        if ($this->cron_expression) {
            $previousFiring = Carbon::instance(
                (new CronExpression($this->cron_expression))->getPreviousRunDate(now()->toDateTimeString())
            );

            if (now()->lessThan($previousFiring->copy()->addMinutes($this->graceMinutes()))) {
                return false;
            }

            return $this->last_checked_in_at === null
                || $this->last_checked_in_at->lessThan($previousFiring);
        }

        $reference = $this->last_checked_in_at ?? $this->created_at;
        $deadline = $reference->copy()->addMinutes($this->periodMinutes() + $this->graceMinutes());

        return now()->greaterThanOrEqualTo($deadline);
    }

    public function nextScheduledAfter(Carbon $after): Carbon
    {
        if ($this->cron_expression) {
            return Carbon::instance(
                (new CronExpression($this->cron_expression))->getNextRunDate($after->toDateTimeString())
            );
        }

        return $after->copy()->addMinutes($this->periodMinutes());
    }

    public function periodMinutes(): int
    {
        return intdiv($this->schedule_interval->toMinutes(), max($this->schedule_frequency, 1));
    }

    public function graceMinutes(): int
    {
        return $this->grace_units->toMinutes($this->grace_value);
    }

    public function recordCheckIn(?string $sourceIp = null, ?Carbon $at = null): CheckIn
    {
        $at ??= now();

        $checkIn = $this->checkIns()->create([
            'checked_in_at' => $at,
            'source_ip' => $sourceIp,
        ]);

        $this->last_checked_in_at = $at;
        $this->alerting_since = null;
        $this->last_alerted_at = null;
        $this->save();

        return $checkIn;
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
        if ($this->silenced_until?->isFuture()) {
            return true;
        }

        $owner = $this->team_id ? $this->team : $this->user;

        return (bool) $owner?->silenced_until?->isFuture();
    }
}
