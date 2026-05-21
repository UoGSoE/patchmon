<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'notification_email',
        'sender_email',
        'silenced_until',
        'silence_reason',
    ];

    protected function casts(): array
    {
        return [
            'silenced_until' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
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
