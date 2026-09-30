<?php

namespace App\Models;

use App\Enums\RealizationStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Realization extends Model
{
    use HasFactory;

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

    /**
     * Field turunan (additif) agar UI/API dapat membedakan "direkomendasikan
     * Sekretaris" dari "disahkan" tanpa menebak dari string status.
     */
    protected $appends = ['is_final', 'status_label'];

    public function target()
    {
        return $this->belongsTo(Target::class);
    }

    public function inputBy()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    public function approvalHistory()
    {
        return $this->hasMany(ApprovalHistory::class)->orderBy('id');
    }

    protected function isFinal(): Attribute
    {
        return Attribute::get(fn (): bool => $this->statusEnum()?->isFinal() ?? false);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->statusEnum()?->label() ?? (string) $this->status
        );
    }

    private function statusEnum(): ?RealizationStatus
    {
        return RealizationStatus::tryFrom((string) $this->status);
    }
}