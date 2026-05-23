<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_staff' => 'boolean',
        ];
    }

    protected $fillable = [
        'username',
        'forenames',
        'surname',
        'email',
        'password',
        'is_admin',
        'is_staff',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->forenames.' '.$this->surname;
    }
}
