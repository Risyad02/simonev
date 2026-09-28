<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealizationAttachment extends Model
{
    protected $fillable = [
        'realization_id',
        'file_path',
        'uploaded_by',
    ];

    public function realization()
    {
        return $this->belongsTo(Realization::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}