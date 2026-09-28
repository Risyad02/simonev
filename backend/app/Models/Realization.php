<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Realization extends Model
{
    protected $fillable = [
        'target_id',
        'realization_value',
        'achievement_pct',
        'deviation',
        'status',
        'input_by',
        'input_at',
    ];

    protected $casts = [
        'realization_value' => 'decimal:4',
        'achievement_pct'   => 'decimal:4',
        'deviation'         => 'decimal:4',
        'input_at'          => 'datetime',
    ];

    public function target()
    {
        return $this->belongsTo(Target::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}