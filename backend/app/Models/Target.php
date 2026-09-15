<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    use HasFactory;

    protected $fillable = [
        'indicator_version_id',
        'planning_document_id',
        'period_label',
        'target_value',
        'revision_no',
        'is_active',
        'valid_from',
        'valid_to',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'revision_no'  => 'integer',
        'is_active'    => 'boolean',
        'valid_from'   => 'datetime',
        'valid_to'     => 'datetime',
    ];

    public function indicatorVersion()
    {
        return $this->belongsTo(IndicatorVersion::class);
    }

    public function planningDocument()
    {
        return $this->belongsTo(PlanningDocument::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Catatan: relasi realizations() SENGAJA tidak didefinisikan di sini.
    // Model Realization belum ada (scope Phase 10). Pengecekan keberadaan
    // realization terkait target dilakukan via Query Builder langsung
    // (DB::table('realizations')) di TargetService, bukan relasi Eloquent.
    // Lihat TargetService::delete() untuk implementasi guard.
}