<?php

namespace App\Services\MasterData;

use App\Models\ReportingPeriod;
use Illuminate\Support\Facades\DB;

class ReportingPeriodService
{
    public function list()
    {
        return ReportingPeriod::query()->orderBy('name')->get();
    }

    public function create(array $data): ReportingPeriod
    {
        return ReportingPeriod::create($data);
    }

    public function update(ReportingPeriod $reportingPeriod, array $data): ReportingPeriod
    {
        $reportingPeriod->update($data);

        return $reportingPeriod;
    }

    public function isReferenced(ReportingPeriod $reportingPeriod): bool
    {
        return DB::table('indicator_versions')
            ->where('reporting_period_id', $reportingPeriod->id)
            ->exists();
    }

    public function delete(ReportingPeriod $reportingPeriod): bool
    {
        if ($this->isReferenced($reportingPeriod)) {
            return false;
        }

        $reportingPeriod->delete();

        return true;
    }
}