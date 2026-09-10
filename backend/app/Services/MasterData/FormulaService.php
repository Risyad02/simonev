<?php

namespace App\Services\MasterData;

use App\Models\Formula;
use Illuminate\Support\Facades\DB;

class FormulaService
{
    public function list()
    {
        return Formula::query()->orderBy('name')->get();
    }

    public function create(array $data): Formula
    {
        return Formula::create($data);
    }

    public function update(Formula $formula, array $data): Formula
    {
        $formula->update($data);

        return $formula;
    }

    public function isReferenced(Formula $formula): bool
    {
        return DB::table('indicator_versions')
            ->where('formula_id', $formula->id)
            ->exists();
    }

    public function delete(Formula $formula): bool
    {
        if ($this->isReferenced($formula)) {
            return false;
        }

        $formula->delete();

        return true;
    }
}