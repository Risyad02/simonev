<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanningDocument extends Model
{
    use HasFactory;

    protected $table = 'planning_documents';

    protected $fillable = [
        'parent_document_id',
        'document_type',
        'year',
        'period_start_year',
        'period_end_year',
        'version_no',
        'status',
        'created_by',
        'updated_by',
    ];

    public function parentDocument()
    {
        return $this->belongsTo(PlanningDocument::class, 'parent_document_id');
    }

    public function performanceStructures()
    {
        return $this->hasMany(PerformanceStructure::class);
    }
}