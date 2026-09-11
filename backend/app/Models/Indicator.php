<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'structure_id',
        'name',
    ];

    public function structure()
    {
        return $this->belongsTo(PerformanceStructure::class, 'structure_id');
    }

    public function versions()
    {
        return $this->hasMany(IndicatorVersion::class);
    }

    public function activeVersion()
    {
        return $this->hasOne(IndicatorVersion::class)->where('is_active', true);
    }
}