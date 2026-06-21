<?php

namespace App\Models;

use Database\Factories\EstateSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstateSnapshot extends Model
{
    /** @use HasFactory<EstateSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'snapshot_date',
        'total',
        'overdue',
        'silenced',
        'patched_30d',
        'never_checked_in',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
        ];
    }
}
