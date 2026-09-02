<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    protected $fillable = ['name', 'formula_type', 'expression', 'description'];
}