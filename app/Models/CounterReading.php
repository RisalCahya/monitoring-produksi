<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterReading extends Model
{
    protected $fillable = [
        'machine_id',
        'counter_value',
        'observed_at',
        'received_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'counter_value' => 'string',
            'observed_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
