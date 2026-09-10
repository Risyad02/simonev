<?php

namespace App\Services\MasterData;

use App\Models\Unit;

class UnitService
{
    public function list()
    {
        return Unit::query()->orderBy('name')->get();
    }

    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    /**
     * @return array{0: bool, 1: ?string, 2: Unit}
     */
    public function update(Unit $unit, array $data): array
    {
        if (array_key_exists('parent_unit_id', $data) && $this->isCircular($unit, $data['parent_unit_id'])) {
            return [false, 'Parent unit tidak boleh membentuk struktur hierarki melingkar (circular).', $unit];
        }

        $unit->update($data);

        return [true, null, $unit];
    }

    public function hasActiveUsers(Unit $unit): bool
    {
        return $unit->users()->where('is_active', true)->exists();
    }

    public function hasActiveChildren(Unit $unit): bool
    {
        return $unit->children()->where('is_active', true)->exists();
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    public function deactivate(Unit $unit): array
    {
        if ($this->hasActiveUsers($unit)) {
            return [false, 'Unit tidak dapat dinonaktifkan karena masih memiliki pengguna aktif. Pindahkan pengguna terlebih dahulu.'];
        }

        if ($this->hasActiveChildren($unit)) {
            return [false, 'Unit tidak dapat dinonaktifkan karena masih memiliki unit anak yang aktif. Nonaktifkan atau pindahkan unit anak terlebih dahulu.'];
        }

        $unit->update(['is_active' => false]);

        return [true, null];
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    public function activate(Unit $unit): array
    {
        if ($unit->parent_unit_id) {
            $parent = $unit->parent;

            if ($parent && ! $parent->is_active) {
                return [false, 'Unit tidak dapat diaktifkan karena unit induk sedang nonaktif.'];
            }
        }

        $unit->update(['is_active' => true]);

        return [true, null];
    }

    public function isCircular(Unit $unit, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }

        if ($newParentId === $unit->id) {
            return true;
        }

        $current = Unit::find($newParentId);

        while ($current) {
            if ($current->id === $unit->id) {
                return true;
            }

            $current = $current->parent_unit_id ? Unit::find($current->parent_unit_id) : null;
        }

        return false;
    }
}