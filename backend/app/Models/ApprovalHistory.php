<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Jejak transisi status Realization. Append-only: update dan delete lewat
 * Eloquent ditolak. Operasi massal via Query Builder tidak melewati event
 * model dan tidak tercegah di level ini.
 */
class ApprovalHistory extends Model
{
    /** Tabel memiliki updated_at (nullable), tetapi baris tidak pernah diubah. */
    const UPDATED_AT = null;

    protected $table = 'approval_history';

    protected $fillable = [
        'realization_id',
        'actor_id',
        'from_status',
        'to_status',
        'action',
        'note',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('ApprovalHistory bersifat append-only dan tidak dapat diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('ApprovalHistory bersifat append-only dan tidak dapat dihapus.');
        });
    }

    public function realization()
    {
        return $this->belongsTo(Realization::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}