<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingPeriod extends Model
{
    protected $fillable = ['name', 'periods_per_year'];
}