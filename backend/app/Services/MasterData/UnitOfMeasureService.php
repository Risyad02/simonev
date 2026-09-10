<?php

namespace App\Services\MasterData;

use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\DB;

class UnitOfMeasureService
{
    public function list()
    {
        return UnitOfMeasure::query()->orderBy('name')->get();
    }

    public function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::create($data);
    }

    public function update(UnitOfMeasure $unitOfMeasure, array $data): UnitOfMeasure
    {
        $unitOfMeasure->update($data);

        return $unitOfMeasure;
    }

    public function isReferenced(UnitOfMeasure $unitOfMeasure): bool
    {
        return DB::table('indicator_versions')
            ->where('unit_of_measure_id', $unitOfMeasure->id)
            ->exists();
    }

    public function delete(UnitOfMeasure $unitOfMeasure): bool
    {
        if ($this->isReferenced($unitOfMeasure)) {
            return false;
        }

        $unitOfMeasure->delete();

        return true;
    }
}