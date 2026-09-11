<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceStructure extends Model
{
    use HasFactory;

    protected $table = 'performance_structure';

    protected $fillable = [
        'parent_id',
        'planning_document_id',
        'level_type',
        'name',
        'year',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(PerformanceStructure::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(PerformanceStructure::class, 'parent_id');
    }

    public function planningDocument()
    {
        return $this->belongsTo(PlanningDocument::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}