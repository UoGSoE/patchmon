<?php

namespace App\Models;

use Database\Factories\PatchEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatchEvent extends Model
{
    /** @use HasFactory<PatchEventFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id',
        'patched_at',
        'source_ip',
    ];

    protected function casts(): array
    {
        return [
            'patched_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
