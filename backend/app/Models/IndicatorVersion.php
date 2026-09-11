<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndicatorVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'indicator_id',
        'planning_document_id',
        'unit_of_measure_id',
        'formula_id',
        'reporting_period_id',
        'direction_id',
        'operational_definition',
        'measurement_method',
        'data_source',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unitOfMeasure()
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    public function formula()
    {
        return $this->belongsTo(Formula::class);
    }

    public function reportingPeriod()
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function direction()
    {
        return $this->belongsTo(MeasurementDirection::class, 'direction_id');
    }

    public function planningDocument()
    {
        return $this->belongsTo(PlanningDocument::class);
    }
}