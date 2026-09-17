<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Tabel audit_logs bersifat append-only — tidak ada kolom updated_at
     * sejak migration Phase 3, jadi Eloquent tidak boleh mencoba menulisnya.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];
}