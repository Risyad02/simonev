<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

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

    /**
     * Append-only ditegakkan juga di level model (Phase 11): nilai lama hasil
     * koreksi realisasi hanya tersimpan di sini, sehingga baris audit tidak
     * boleh diubah atau dihapus lewat Eloquent. Catatan: operasi massal via
     * Query Builder tidak melewati event model dan tidak tercegah di level ini.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('AuditLog bersifat append-only dan tidak dapat diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('AuditLog bersifat append-only dan tidak dapat dihapus.');
        });
    }
}