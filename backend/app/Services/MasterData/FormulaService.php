<?php

namespace App\Services\MasterData;

use App\Models\Formula;
use Illuminate\Support\Facades\DB;

class FormulaService
{
    private const PROTECTED_FIELDS = ['expression', 'formula_type', 'type'];

    public function list()
    {
        return Formula::query()->orderBy('name')->get();
    }

    public function create(array $data): Formula
    {
        return Formula::create($data);
    }

    /**
     * @return array{0: bool, 1: Formula|string, 2: int|null}
     */
    public function update(Formula $formula, array $data): array
    {
        if ($this->isReferenced($formula)) {
            foreach (self::PROTECTED_FIELDS as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== $formula->{$field}) {
                    return [
                        false,
                        "Formula tidak dapat diubah pada field '{$field}' karena sudah digunakan oleh indicator version. Buat formula baru dan revisi indicator version jika ingin mengubah kalkulasi.",
                        409,
                    ];
                }
            }
        }

        $formula->update($data);

        return [true, $formula, null];
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